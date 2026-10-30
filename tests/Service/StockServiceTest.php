<?php

namespace App\Tests\Service;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\InventoryReservation;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use App\Entity\FlashSaleEvent;
use App\Repository\ProductRepository;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StockServiceTest extends TestCase
{
    public function testReservationRejectsQuantityLessThanOne(): void
    {
        $entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        $productRepository = $this->createMock(
            ProductRepository::class
        );

        $entityManager
            ->expects(self::never())
            ->method('wrapInTransaction');

        $stockService = new StockService(
            $entityManager,
            $productRepository
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Quantity must be at least 1.'
        );

        $stockService->reserve(
            1,
            1,
            0,
            new FlashSaleEvent()
        );
    }



    public function testReservationRejectsQuantityGreaterThanAvailableStock(): void
    {
    $entityManager = $this->createMock(
        EntityManagerInterface::class
    );

    $productRepository = $this->createMock(
        ProductRepository::class
    );

    $product = $this->createMock(Product::class);
    $product->method('getId')->willReturn(1);
    $product->method('getStatus')->willReturn('ACTIVE');
    $product->method('getInitialStock')->willReturn(5);

    $user = $this->createMock(User::class);

    $event = $this->createMock(FlashSaleEvent::class);
    $event->method('getProduct')->willReturn($product);
    $event->method('getMaxQuantityPerUser')->willReturn(10);
    $event->method('getStartTime')
        ->willReturn(new \DateTimeImmutable('-1 hour'));
    $event->method('getEndTime')
        ->willReturn(new \DateTimeImmutable('+1 hour'));

    $productRepository
        ->method('findForUpdate')
        ->with(1)
        ->willReturn($product);

    $userRepository = $this->createMock(EntityRepository::class);
    $userRepository
        ->method('find')
        ->with(1)
        ->willReturn($user);

    $entityManager
        ->method('getRepository')
        ->with(User::class)
        ->willReturn($userRepository);

    $query = $this->createMock(Query::class);
    $query->method('setParameter')->willReturnSelf();
    $query->method('getSingleScalarResult')->willReturn(0);

    $entityManager
        ->method('createQuery')
        ->willReturn($query);

    $entityManager
        ->method('wrapInTransaction')
        ->willReturnCallback(
            static fn (callable $callback) => $callback()
        );

    $entityManager
        ->expects(self::never())
        ->method('persist');

    $stockService = new StockService(
        $entityManager,
        $productRepository
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Not enough stock available.');

    $stockService->reserve(
        1,
        1,
        6,
        $event
    );
    }

    
    public function testReservationSucceedsWhenStockIsAvailable(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepository::class);

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getStatus')->willReturn('ACTIVE');
        $product->method('getInitialStock')->willReturn(10);

        $user = $this->createMock(User::class);

        $event = $this->createMock(FlashSaleEvent::class);
        $event->method('getProduct')->willReturn($product);
        $event->method('getMaxQuantityPerUser')->willReturn(5);
        $event->method('getStartTime')
            ->willReturn(new \DateTimeImmutable('-1 hour'));
        $event->method('getEndTime')
            ->willReturn(new \DateTimeImmutable('+1 hour'));

        $productRepository
            ->method('findForUpdate')
            ->with(1)
            ->willReturn($product);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('find')->with(1)->willReturn($user);

        $entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepository);

        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);

        $entityManager->method('createQuery')->willReturn($query);

        $entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(
                static fn (callable $callback) => $callback()
        );

    $entityManager
        ->expects(self::once())
        ->method('persist')
        ->with(self::isInstanceOf(InventoryReservation::class));

    $entityManager
        ->expects(self::once())
        ->method('flush');

    $stockService = new StockService(
        $entityManager,
        $productRepository
    );

    $result = $stockService->reserve(1, 1, 2, $event);

    $this->assertInstanceOf(InventoryReservation::class, $result);
    $this->assertSame('RESERVED', $result->getStatus());
    $this->assertSame(2, $result->getQuantity());

    $this->assertGreaterThanOrEqual(
        599,
        $result->getExpiresAt()->getTimestamp() - time()
    );

    $this->assertLessThanOrEqual(
        600,
        $result->getExpiresAt()->getTimestamp() - time()
    );
    }


}
