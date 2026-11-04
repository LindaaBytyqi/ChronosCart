<?php

namespace App\Dto;

use App\Entity\FlashSaleEvent;
use App\Entity\User;
use App\Validator\UserFlashSaleLimit;
use Symfony\Component\Validator\Constraints as Assert;

#[UserFlashSaleLimit]
class ReserveRequest
{
    #[Assert\NotNull(message: 'Quantity is required.')]
    #[Assert\Type(type: 'integer', message: 'Quantity must be an integer.')]
    #[Assert\Positive(message: 'Quantity must be greater than zero.')]
    public ?int $quantity = null;

    public ?FlashSaleEvent $event = null;

    public ?User $user = null;

    public ?string $identityHash = null;
}
