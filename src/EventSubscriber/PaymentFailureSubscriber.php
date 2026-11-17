<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Message\ProcessPaymentMessage;
use App\Message\RestockInventoryMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final class PaymentFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onPaymentFailed'];
    }

    public function onPaymentFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ProcessPaymentMessage || $event->willRetry()) {
            return;
        }

        $this->messageBus->dispatch(new RestockInventoryMessage($message->orderId));
    }
}