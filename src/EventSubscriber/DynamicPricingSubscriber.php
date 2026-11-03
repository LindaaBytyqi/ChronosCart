<?php

namespace App\EventSubscriber;

use App\Event\CartItemAddedEvent;
use App\Event\InventoryReservedEvent;
use App\Service\DynamicPricingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DynamicPricingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DynamicPricingService $dynamicPricingService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InventoryReservedEvent::class => 'onInventoryReserved',
            CartItemAddedEvent::class => 'onCartItemAdded',
        ];
    }

    public function onInventoryReserved(InventoryReservedEvent $event): void
    {
        $reservation = $event->getReservation();

        $this->dynamicPricingService->evaluate(
            $reservation->getProduct()
        );
    }

    public function onCartItemAdded(CartItemAddedEvent $event): void
    {
        $this->dynamicPricingService->evaluate(
            $event->getProduct()
        );
    }
}
