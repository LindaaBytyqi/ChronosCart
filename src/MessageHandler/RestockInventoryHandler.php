<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\RestockInventoryMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestockInventoryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RestockInventoryMessage $message): void
    {
        $this->entityManager->wrapInTransaction(
            function () use ($message): void {
                $order = $this->entityManager->find(
                    Order::class,
                    $message->orderId,
                    LockMode::PESSIMISTIC_WRITE
                );

                if (!$order || $order->getStatus() !== 'PENDING') {
                    return;
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
                    return;
                }

                $order->setStatus('FAILED');

                $this->entityManager->flush();
            }
        );
    }
}