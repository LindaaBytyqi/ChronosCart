<?php

namespace App\Security;

use App\Entity\FlashSaleEvent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FlashSaleVoter extends Voter
{
    public const RESERVE = 'FLASH_SALE_RESERVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::RESERVE && $subject instanceof FlashSaleEvent;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var FlashSaleEvent $subject */
        $now = new \DateTimeImmutable();

        return $subject->getProduct()?->getStatus() === 'ACTIVE'
            && $subject->getStartTime() !== null
            && $subject->getEndTime() !== null
            && $now >= $subject->getStartTime()
            && $now < $subject->getEndTime();
    }
}