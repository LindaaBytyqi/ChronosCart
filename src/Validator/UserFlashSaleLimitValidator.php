<?php

namespace App\Validator;

use App\Dto\ReserveRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use App\Entity\User;

class UserFlashSaleLimitValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UserFlashSaleLimit) {
            throw new UnexpectedTypeException(
                $constraint,
                UserFlashSaleLimit::class
            );
        }

        if (!$value instanceof ReserveRequest) {
            throw new UnexpectedValueException(
                $value,
                ReserveRequest::class
            );
        }

        // Kontrollojmë të dhënat bazë
        if (
            $value->event === null
            || $value->quantity === null
            || $value->quantity < 1
        ) {
            return;
        }

        $user = $value->user;
        $identityHash = $value->identityHash;

        if ($user === null && $identityHash === null) {
            return;
        }

        $maxQuantity = $value->event->getMaxQuantityPerUser();

        if ($maxQuantity === null || $maxQuantity < 1) {
            return;
        }

        $conditions = [];

        if ($user !== null) {
            $conditions[] = 'r.user = :user';
        }

        if ($identityHash !== null) {
            $conditions[] = 'r.identityHash = :identityHash';
        }

        $now = new \DateTimeImmutable();

        $query = $this->entityManager->createQuery(
            'SELECT COALESCE(SUM(r.quantity), 0)
             FROM App\Entity\InventoryReservation r
             WHERE r.product = :product
             AND r.flashSaleEvent = :event
             AND (
                 (r.status = :reserved AND r.expiresAt > :now)
                 OR r.status = :converted
             )
             AND (' . implode(' OR ', $conditions) . ')'
        );

        $query
            ->setParameter('product', $value->event->getProduct())
            ->setParameter('event', $value->event)
            ->setParameter('reserved', 'RESERVED')
            ->setParameter('converted', 'CONVERTED')
            ->setParameter('now', $now);

        if ($user !== null) {
            $query->setParameter('user', $user);
        }

        if ($identityHash !== null) {
            $query->setParameter('identityHash', $identityHash);
        }

        $alreadyReserved = (int) $query->getSingleScalarResult();

        if ($alreadyReserved + $value->quantity > $maxQuantity) {
            $this->context
                ->buildViolation($constraint->message)
                ->atPath('quantity')
                ->addViolation();
        }
    }
}
