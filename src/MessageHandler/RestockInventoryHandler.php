<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\RestockInventoryMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
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

                if (!$order) {
                    throw new RuntimeException('Order not found.');
                }

                if ($order->getStatus() === 'FAILED') {
                    return;
                }

                if ($order->getStatus() !== 'PENDING') {
                    return;
                }

                $matchingQuantity = 0;

                foreach ($order->getOrderItems() as $item) {
                    if (
                        $item->getProduct()->getId() === $message->productId
                    ) {
                        $matchingQuantity += $item->getQuantity();
                    }
                }

                if ($matchingQuantity !== $message->quantity) {
                    throw new RuntimeException(
                        'Restock quantity does not match order items.'
                    );
                }

                $order->setStatus('FAILED');

                $this->entityManager->flush();
            }
       );
    }
}
