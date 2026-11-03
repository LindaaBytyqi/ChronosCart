<?php

namespace App\Service;

use App\Entity\PriceLog;
use App\Entity\Product;
use App\Repository\PriceLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class DynamicPricingService
{
    private const RESERVATION_THRESHOLD = 10;
    private const PRICE_INCREASE_PER_100 = 5;
    private const MAX_INCREASE_PERCENTAGE = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PriceLogRepository $priceLogRepository,
    ) {
    }

    public function evaluate(Product $product): void
    {
        $now = new \DateTimeImmutable();
        $oneMinuteAgo = $now->modify('-1 minute');

        $recentReservations = $this->entityManager
            ->createQueryBuilder()
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->from(\App\Entity\InventoryReservation::class, 'r')
            ->where('r.product = :product')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('product', $product)
            ->setParameter('since', $oneMinuteAgo)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $recentReservations < self::RESERVATION_THRESHOLD) {
            return;
        }
    
        $soldUnits = $this->entityManager
            ->createQueryBuilder()
            ->select('COALESCE(SUM(oi.quantity), 0)')
            ->from(\App\Entity\OrderItems::class, 'oi')
            ->join('oi.purchaseOrder', 'o')
            ->where('oi.product = :product')
            ->andWhere('o.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', 'PAID')
            ->getQuery()
            ->getSingleScalarResult();

        $levels = intdiv((int) $soldUnits, 100);

        $increasePercentage = min(
            $levels * self::PRICE_INCREASE_PER_100,
            self::MAX_INCREASE_PERCENTAGE
        );

        if ($increasePercentage === 0) {
            return;
        }

        $basePrice = (float) $product->getBasePrice();

        $newPrice = round(
            $basePrice * (1 + $increasePercentage / 100),
            2
        );

        $latestLog = $this->priceLogRepository
            ->findOneBy(
                ['product' => $product],
                ['recordedAt' => 'DESC']
            );

        $currentPrice = $latestLog
            ? (float) $latestLog->getPrice()
            : $basePrice;

        if (abs($newPrice - $currentPrice) < 0.001) {
            return;
        }

        $priceLog = new PriceLog();
        $priceLog->setProduct($product);
        $priceLog->setPrice(number_format($newPrice, 2, '.', ''));
        $priceLog->setRecordedAt($now);

        $this->entityManager->persist($priceLog);
        $this->entityManager->flush();
    }
    
    public function getCurrentPrice(Product $product): string
    {
        $latestLog = $this->priceLogRepository->findOneBy(
            ['product' => $product],
            ['recordedAt' => 'DESC']
        );

        return $latestLog
            ? $latestLog->getPrice()
            : $product->getBasePrice();
    }
}
