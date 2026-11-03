<?php

namespace App\Event;

use App\Entity\Product;

class CartItemAddedEvent
{
    public function __construct(
        private readonly Product $product,
        private readonly int $quantity
    ) {
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
