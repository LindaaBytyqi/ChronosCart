<?php

namespace App\Event;

use App\Entity\InventoryReservation;

class InventoryReservedEvent
{
    public function __construct(
        private readonly InventoryReservation $reservation
    ) {
    }

    public function getReservation(): InventoryReservation
    {
        return $this->reservation;
    }
}
