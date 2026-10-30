<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\ProcessPaymentMessage;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessPaymentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ProcessPaymentMessage $message): void
    {
        $order = $this->entityManager->find(
            Order::class,
            $message->orderId
        );

        if (!$order) {
            throw new RuntimeException(
                'Order not found: ' . $message->orderId
            );
        }

        if ($order->getStatus() !== 'PENDING') {
            return;
        }

        if ($order->getPaymentMethod() === 'SIMULATE_FAILURE') {
            throw new RuntimeException(
                'Simulated payment gateway failure.'
            );
        }

        $order->setStatus('PAID');

        $this->entityManager->flush();
    }
}
