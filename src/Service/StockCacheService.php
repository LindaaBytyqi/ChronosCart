<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StockCacheService
{
    private const CACHE_TTL = 60;

    public function __construct(
        #[Autowire(service: 'cache.stock')]
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Retrieves stock from cache or loads it from the database.
     */
    public function getOrLoadStock(
        int $productId,
        callable $stockLoader
    ): int {
        return $this->cache->get(
            $this->getCacheKey($productId),
            function (ItemInterface $item) use ($stockLoader): int {
                $item->expiresAfter(self::CACHE_TTL);

                return $stockLoader();
            }
        );
    }

    /**
     * Removes cached stock after an inventory change.
     */
    public function invalidateStock(int $productId): void
    {
        $this->cache->delete(
            $this->getCacheKey($productId)
        );
    }

    private function getCacheKey(int $productId): string
    {
        return 'flash_sale_stock_' . $productId;
    }
}
