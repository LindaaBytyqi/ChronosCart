<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\ProcessPaymentMessage;
use Doctrine\DBAL\LockMode;
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

                if ($order->getStatus() !== 'PENDING') {
                    return;
                }

                if ($order->getPaymentMethod() !== 'online') {
                    return;
                }

       
                $order->setStatus('PAID');

                $this->entityManager->flush();
            }
        );
    }
}