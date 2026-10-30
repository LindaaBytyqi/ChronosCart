<?php

namespace App\Message;

final readonly class RestockInventoryMessage
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public int $orderId,
    ) {
    }
}