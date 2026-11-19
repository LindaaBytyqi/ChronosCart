<?php

namespace App\Tests\Service;

use App\Repository\PriceLogRepository;
use App\Service\DynamicPricingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    private DynamicPricingService $service;

    protected function setUp(): void
    {
        $this->service = new DynamicPricingService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(PriceLogRepository::class),
        );
    }

    #[DataProvider('surgeProvider')]
    public function testSurgePercentage(int $sold, int $expected): void
    {
        $this->assertSame($expected, $this->service->calculateSurgePercentage($sold));
    }

    public static function surgeProvider(): array
    {
        return [
            'asnjë shitje' => [0, 0],
            'nën 100' => [99, 0],
            '100 njësi' => [100, 5],
            '250 njësi' => [250, 10],
            'kufiri maksimal' => [600, 30],
            'mbi kufi' => [10000, 30],
            'negative' => [-5, 0],
        ];
    }

    public function testSurgedPrice(): void
    {
        $this->assertSame('100.00', $this->service->calculateSurgedPrice('100.00', 0));
        $this->assertSame('105.00', $this->service->calculateSurgedPrice('100.00', 5));
        $this->assertSame('130.00', $this->service->calculateSurgedPrice('100.00', 30));
        $this->assertSame('21.99', $this->service->calculateSurgedPrice('19.99', 10));
    }
}