<?php

namespace App\Service;

use App\Entity\InventoryReservation;
use Doctrine\ORM\EntityManagerInterface;

class ReservationExpiryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function expireReservations(): int
    {
        $now = new \DateTimeImmutable();

        $reservations = $this->entityManager
            ->getRepository(InventoryReservation::class)
            ->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt <= :now')
            ->setParameter('status', 'RESERVED')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($reservations as $reservation) {
            $reservation->setStatus('EXPIRED');
        }

        $this->entityManager->flush();

        return count($reservations);
    }
}
