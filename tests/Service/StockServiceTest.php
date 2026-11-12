<?php

namespace App\Tests\Service;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use App\Entity\FlashSaleEvent;
use App\Entity\InventoryReservation;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\StockCacheService;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class StockServiceTest extends KernelTestCase
{
    private function createStockService(
    EntityManagerInterface $entityManager,
    ProductRepository $productRepository,
    EventDispatcherInterface $eventDispatcher,
    
    StockCacheService $stockCacheService
): StockService {
    $lockFactory = new LockFactory(new FlockStore());

    return new StockService(
        $entityManager,
        $productRepository,
        $eventDispatcher,
        $stockCacheService,
        $lockFactory
    );
}

    public function testReservationRejectsQuantityLessThanOne(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $stockCacheService = $this->createMock(StockCacheService::class);

        $service = $this->createStockService(
            $entityManager,
            $productRepository,
            $eventDispatcher,
            $stockCacheService
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->reserve(1, 1, 0, new FlashSaleEvent());
    }

    public function testReservationRejectsQuantityGreaterThanAvailableStock(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $stockCacheService = $this->createMock(StockCacheService::class);

        $product = new Product();
        $this->setProductId($product, 1);
        $product->setInitialStock(5);
        $product->setStatus('ACTIVE');

        $event = new FlashSaleEvent();
        $event->setProduct($product);
        $event->setStartTime(new \DateTimeImmutable('-1 hour'));
        $event->setEndTime(new \DateTimeImmutable('+1 hour'));

        $productRepository
            ->method('findForUpdate')
            ->with(1)
            ->willReturn($product);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(
                fn (callable $callback) => $callback($entityManager)
            );

        $service = $this->createStockService(
            $entityManager,
            $productRepository,
            $eventDispatcher,
            $stockCacheService
        );

        $this->expectException(\RuntimeException::class);

        $service->reserve(1, 1, 6, $event);
    }

    public function testReservationSucceedsWhenStockIsAvailable(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $stockCacheService = $this->createMock(StockCacheService::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);

        $product = new Product();
        $this->setProductId($product, 1);
        $product->setInitialStock(10);
        $product->setStatus('ACTIVE');

        $event = new FlashSaleEvent();
        $event->setProduct($product);
        $event->setStartTime(new \DateTimeImmutable('-1 hour'));
        $event->setEndTime(new \DateTimeImmutable('+1 hour'));
        $event->setMaxQuantityPerUser(5);

        $user = new User();

        $productRepository
            ->method('findForUpdate')
            ->with(1)
            ->willReturn($product);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $userRepository
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepository);

        $entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(
                fn (callable $callback) => $callback($entityManager)
            );

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(InventoryReservation::class));

        $entityManager
            ->expects(self::once())
            ->method('flush');

        $stockCacheService
            ->expects(self::once())
            ->method('invalidateStock')
            ->with(1);

        $service = $this->createStockService(
            $entityManager,
            $productRepository,
            $eventDispatcher,
            $stockCacheService
        );

        $reservation = $service->reserve(1, 1, 2, $event);

        self::assertInstanceOf(InventoryReservation::class, $reservation);
        self::assertSame(2, $reservation->getQuantity());
    }

    public function testUserCannotExceedFlashSaleLimit(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $stockCacheService = $this->createMock(StockCacheService::class);

        $product = new Product();
        $this->setProductId($product, 1);
        $product->setInitialStock(10);
        $product->setStatus('ACTIVE');

        $event = new FlashSaleEvent();
        $event->setProduct($product);
        $event->setStartTime(new \DateTimeImmutable('-1 hour'));
        $event->setEndTime(new \DateTimeImmutable('+1 hour'));
        $event->setMaxQuantityPerUser(3);

        $productRepository
            ->method('findForUpdate')
            ->with(1)
            ->willReturn($product);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(2);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(
                fn (callable $callback) => $callback($entityManager)
            );

        $service = $this->createStockService(
            $entityManager,
            $productRepository,
            $eventDispatcher,
            $stockCacheService
        );

        $this->expectException(\RuntimeException::class);

        $service->reserve(1, 1, 2, $event);
    }
    private function setProductId(Product $product, int $id): void
    {
        $reflection = new \ReflectionProperty(Product::class, 'id');
        $reflection->setValue($product, $id);
    }
}
