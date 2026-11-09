<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UserFlashSaleLimit extends Constraint
{
    public string $message = 'You have reached the maximum quantity for this flash sale.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}