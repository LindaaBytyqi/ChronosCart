<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\FlashSaleEvent;
use App\Entity\InventoryReservation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StockConcurrencyTest extends KernelTestCase
{
    public function testStockCannotBeOversold(): void
    {
        self::bootKernel();

        $container = static::getContainer();

        $entityManager = $container
            ->get('doctrine')
            ->getManager();

        $product = (new Product())
            ->setSku('CONCURRENCY-' . bin2hex(random_bytes(5)))
            ->setName('Concurrency Test Product')
            ->setBasePrice('100.00')
            ->setInitialStock(1)
            ->setStatus('ACTIVE');

        $now = new \DateTimeImmutable();

        $event = (new FlashSaleEvent())
            ->setProduct($product)
            ->setStartTime($now->modify('-1 hour'))
            ->setEndTime($now->modify('+1 hour'))
            ->setMaxQuantityPerUser(1)
            ->setDiscountPercentage('10.00');

        $entityManager->persist($event);
        $entityManager->flush();

        $productId = $product->getId();
        $eventId = $event->getId();

        $processes = [];

        $workerPath = dirname(__DIR__) . '/Support/stock_reservation_worker.php';

        for ($i = 0; $i < 10; $i++) {
            $identityHash = hash('sha256', 'worker-' . $i . '-' . uniqid());

            $process = proc_open(
                [
                    PHP_BINARY,
                    $workerPath,
                    (string) $productId,
                    (string) $eventId,
                    $identityHash,
                ],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes
            );

            self::assertIsResource($process);

            fclose($pipes[0]);

            $processes[] = [
                'process' => $process,
                'pipes' => $pipes,
            ];
        }

        $successCount = 0;
        $rejectedCount = 0;

        foreach ($processes as $item) {
            $stdout = trim(stream_get_contents($item['pipes'][1]));
            $stderr = trim(stream_get_contents($item['pipes'][2]));

            fclose($item['pipes'][1]);
            fclose($item['pipes'][2]);

            $exitCode = proc_close($item['process']);

            self::assertSame(
                0,
                $exitCode,
                'Worker process failed: ' . $stderr
            );

            if ($stdout === 'SUCCESS') {
                $successCount++;
            } elseif ($stdout === 'REJECTED') {
                $rejectedCount++;
            } else {
                self::fail('Unexpected worker output: ' . $stdout);
            }
        }

        self::assertSame(1, $successCount);

        self::assertSame(9, $rejectedCount);

        $entityManager->clear();

        $reservedQuantity = $entityManager
            ->createQueryBuilder()
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->from(InventoryReservation::class, 'r')
            ->where('IDENTITY(r.product) = :productId')
            ->andWhere('r.status = :status')
            ->setParameter('productId', $productId)
            ->setParameter('status', 'RESERVED')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(
            1,
            (int) $reservedQuantity,
            'Reserved quantity must never exceed initial stock.'
        );
    }
}