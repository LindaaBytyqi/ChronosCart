<?php

namespace App\Service;

use App\Entity\InventoryReservation;
use Doctrine\ORM\EntityManagerInterface;

class ReservationExpiryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockCacheService $stockCacheService,
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

        $productIds = [];

        foreach ($reservations as $reservation) {
            $productId = $reservation->getProduct()?->getId();

            if ($productId !== null) {
                $productIds[$productId] = true;
            }

            $reservation->setStatus('EXPIRED');
        }

        $this->entityManager->flush();

        foreach (array_keys($productIds) as $productId) {
            $this->stockCacheService->invalidateStock($productId);
        }

        return count($reservations);
    }
}
