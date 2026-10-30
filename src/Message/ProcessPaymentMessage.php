<?php

namespace App\Message;

final readonly class ProcessPaymentMessage
{
    public function __construct(
        public int $orderId,
    ) {
    }
}