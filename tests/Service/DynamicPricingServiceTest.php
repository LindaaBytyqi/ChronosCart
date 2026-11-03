<?php

namespace App\Tests\Service;

use App\Entity\PriceLog;
use App\Entity\Product;
use App\Repository\PriceLogRepository;
use App\Service\DynamicPricingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class DynamicPricingServiceTest extends TestCase
{
    private function createQueryBuilderMock(int $result): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')
            ->willReturn($result);

        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }

    public function testPriceDoesNotIncreaseBelowReservationThreshold(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $priceLogRepository = $this->createMock(PriceLogRepository::class);

        $product = $this->createMock(Product::class);
        $product->method('getBasePrice')->willReturn('100.00');

        $entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock(5));

        $entityManager
            ->expects(self::never())
            ->method('persist');

        $service = new DynamicPricingService(
            $entityManager,
            $priceLogRepository
        );

        $service->evaluate($product);
    }

    public function testPriceIncreasesByFivePercentForOneHundredSoldUnits(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $priceLogRepository = $this->createMock(PriceLogRepository::class);

        $product = $this->createMock(Product::class);
        $product->method('getBasePrice')->willReturn('100.00');

        $entityManager
            ->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls(
                $this->createQueryBuilderMock(10),
                $this->createQueryBuilderMock(100)
            );

        $priceLogRepository
            ->method('findOneBy')
            ->willReturn(null);

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (PriceLog $priceLog) use ($product) {
                self::assertSame($product, $priceLog->getProduct());
                self::assertSame('105.00', $priceLog->getPrice());

                return true;
            }));

        $entityManager
            ->expects(self::once())
            ->method('flush');

        $service = new DynamicPricingService(
            $entityManager,
            $priceLogRepository
        );

        $service->evaluate($product);
    }

    public function testPriceIncreaseDoesNotExceedThirtyPercent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $priceLogRepository = $this->createMock(PriceLogRepository::class);

        $product = $this->createMock(Product::class);
        $product->method('getBasePrice')->willReturn('100.00');

        $entityManager
            ->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls(
                $this->createQueryBuilderMock(10),
                $this->createQueryBuilderMock(1000)
            );

        $priceLogRepository
            ->method('findOneBy')
            ->willReturn(null);

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (PriceLog $priceLog) {
                self::assertSame('130.00', $priceLog->getPrice());

                return true;
            }));

        $entityManager
            ->expects(self::once())
            ->method('flush');

        $service = new DynamicPricingService(
            $entityManager,
            $priceLogRepository
        );

        $service->evaluate($product);
    }

    public function testGetCurrentPriceReturnsBasePriceWhenNoPriceLogExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $priceLogRepository = $this->createMock(PriceLogRepository::class);

        $product = $this->createMock(Product::class);
        $product->method('getBasePrice')->willReturn('100.00');

        $priceLogRepository
            ->method('findOneBy')
            ->willReturn(null);

        $service = new DynamicPricingService(
            $entityManager,
            $priceLogRepository
        );

        self::assertSame('100.00', $service->getCurrentPrice($product));
    }
}
