<?php

namespace App\Validator;

use App\DTO\ReserveRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

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

        if (
            $value->event === null ||
            $value->quantity === null ||
            $value->quantity < 1
        ) {
            return;
        }

        $event = $value->event;
        $product = $event->getProduct();
        $maxQuantity = $event->getMaxQuantityPerUser();

        if ($product === null || $maxQuantity === null || $maxQuantity < 1) {
            return;
        }

        $conditions = [];
        $parameters = [];

        if ($value->user !== null) {
            $conditions[] = 'r.user = :user';
            $parameters['user'] = $value->user;
        }

        if ($value->identityHash !== null) {
            $conditions[] = 'r.identityHash = :identityHash';
            $parameters['identityHash'] = $value->identityHash;
        }

        if ($conditions === []) {
            $this->context
                ->buildViolation('Unable to identify the customer.')
                ->atPath('quantity')
                ->addViolation();

            return;
        }

        $now = new \DateTimeImmutable();

        $dql = '
            SELECT COALESCE(SUM(r.quantity), 0)
            FROM App\Entity\InventoryReservation r
            WHERE r.product = :product
            AND r.flashSaleEvent = :event
            AND (
                (r.status = :reserved AND r.expiresAt > :now)
                OR r.status = :converted
            )
            AND (' . implode(' OR ', $conditions) . ')
        ';

        $query = $this->entityManager->createQuery($dql)
            ->setParameter('product', $product)
            ->setParameter('event', $event)
            ->setParameter('reserved', 'RESERVED')
            ->setParameter('converted', 'CONVERTED')
            ->setParameter('now', $now);

        foreach ($parameters as $name => $parameter) {
            $query->setParameter($name, $parameter);
        }

        $alreadyReserved = (int) $query->getSingleScalarResult();

        if (($alreadyReserved + $value->quantity) > $maxQuantity) {
            $this->context
                ->buildViolation($constraint->message)
                ->atPath('quantity')
                ->addViolation();
        }
    }
}