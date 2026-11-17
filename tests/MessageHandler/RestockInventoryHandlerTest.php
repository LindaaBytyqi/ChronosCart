<?php

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Entity\OrderItems;
use App\Entity\Product;
use App\Message\RestockInventoryMessage;
use App\MessageHandler\RestockInventoryHandler;
use App\Service\StockCacheService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RestockInventoryHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private StockCacheService&MockObject $stockCache;
    private RestockInventoryHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->stockCache = $this->createMock(StockCacheService::class);

        $this->em->method('wrapInTransaction')
            ->willReturnCallback(fn (callable $callback) => $callback($this->em));

        $this->handler = new RestockInventoryHandler($this->em, $this->stockCache);
    }

    private function createItem(?int $productId): OrderItems
    {
        $item = $this->createMock(OrderItems::class);

        if ($productId === null) {
            $item->method('getProduct')->willReturn(null);
        } else {
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($productId);
            $item->method('getProduct')->willReturn($product);
        }

        return $item;
    }

    private function createOrder(string $status, array $items = []): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn($status);
        $order->method('getOrderItems')->willReturn(new ArrayCollection($items));

        return $order;
    }

    public function testPorosiaPendingBehetFailedDheCacheInvalidohet(): void
    {
        $order = $this->createOrder('PENDING', [
            $this->createItem(5),
            $this->createItem(7),
            $this->createItem(5), 
        ]);

        $this->em->method('find')
            ->with(Order::class, 1, LockMode::PESSIMISTIC_WRITE)
            ->willReturn($order);

        $order->expects($this->once())->method('setStatus')->with('FAILED');

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('setParameter')
            ->with('o', $order)->willReturnSelf();
        $query->expects($this->once())->method('execute')->willReturn(1);

        $this->em->expects($this->once())->method('createQuery')->willReturn($query);
        $this->em->expects($this->once())->method('flush');

        $invalidated = [];
        $this->stockCache->expects($this->exactly(2))
            ->method('invalidateStock')
            ->willReturnCallback(function (int $id) use (&$invalidated): void {
                $invalidated[] = $id;
            });

        ($this->handler)(new RestockInventoryMessage(1));

        $this->assertSame([5, 7], $invalidated);
    }

    public function testProduktiMeNullIgnorohet(): void
    {
        $order = $this->createOrder('PENDING', [
            $this->createItem(null),
            $this->createItem(3),
        ]);

        $this->em->method('find')->willReturn($order);

        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $this->em->method('createQuery')->willReturn($query);

        $this->stockCache->expects($this->once())
            ->method('invalidateStock')
            ->with(3);

        ($this->handler)(new RestockInventoryMessage(1));
    }

    public function testPorosiaQeNukEshtePendingNukPreket(): void
    {
        $order = $this->createOrder('PAID', [$this->createItem(5)]);

        $this->em->method('find')->willReturn($order);

        $order->expects($this->never())->method('setStatus');
        $this->em->expects($this->never())->method('createQuery');
        $this->em->expects($this->never())->method('flush');
        $this->stockCache->expects($this->never())->method('invalidateStock');

        ($this->handler)(new RestockInventoryMessage(1));
    }

    public function testPorosiaQeNukEkzistonNukBenAsgje(): void
    {
        $this->em->method('find')->willReturn(null);

        $this->em->expects($this->never())->method('createQuery');
        $this->em->expects($this->never())->method('flush');
        $this->stockCache->expects($this->never())->method('invalidateStock');

        ($this->handler)(new RestockInventoryMessage(1));
    }
}