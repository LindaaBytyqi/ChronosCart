<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\RestockInventoryMessage;
use App\Service\StockCacheService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestockInventoryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockCacheService $stockCacheService,
    ) {
    }

    public function __invoke(RestockInventoryMessage $message): void
    {
        $productIds = $this->entityManager->wrapInTransaction(
            function () use ($message): array {
                $order = $this->entityManager->find(
                    Order::class,
                    $message->orderId,
                    LockMode::PESSIMISTIC_WRITE
                );

                if (!$order || $order->getStatus() !== 'PENDING') {
                    return [];
                }

                $matchingItem = false;

                foreach ($order->getOrderItems() as $item) {
                    if (
                        $item->getProduct()?->getId() === $message->productId
                        && $item->getQuantity() === $message->quantity
                    ) {
                        $matchingItem = true;
                        break;
                    }
                }

                if (!$matchingItem) {
                    return [];
                }

                $productIds = [];

                foreach ($order->getOrderItems() as $item) {
                    $productId = $item->getProduct()?->getId();

                    if ($productId !== null) {
                        $productIds[$productId] = true;
                    }
                }

                $order->setStatus('FAILED');

                $this->entityManager->flush();

                return array_keys($productIds);
            }
        );

        foreach ($productIds as $productId) {
            $this->stockCacheService->invalidateStock($productId);
        }
    }
}
