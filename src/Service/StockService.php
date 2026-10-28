<?php

namespace App\Service;

use App\Entity\FlashSaleEvent;
use App\Entity\InventoryReservation;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function reserve(
        int $productId,
        int $userId,
        int $quantity,
        FlashSaleEvent $event
    ): InventoryReservation {
        if ($quantity < 1) {
            throw new RuntimeException(
                'Quantity must be at least 1.'
            );
        }

        return $this->entityManager->wrapInTransaction(
            function () use ($productId, $userId, $quantity, $event) {
                // Lock the product row to prevent concurrent overselling.
                $product = $this->productRepository
                    ->findForUpdate($productId);

                if ($product === null) {
                    throw new RuntimeException('Product not found.');
                }

                if ($product->getStatus() !== 'ACTIVE') {
                    throw new RuntimeException('Product is not active.');
                }

                if ($event->getProduct()?->getId() !== $productId) {
                    throw new RuntimeException(
                        'The event does not belong to this product.'
                    );
                }

                $user = $this->entityManager
                    ->getRepository(User::class)
                    ->find($userId);

                if (!$user instanceof User) {
                    throw new RuntimeException('User not found.');
                }

                $maxQuantity = $event->getMaxQuantityPerUser();

                if ($maxQuantity === null || $maxQuantity < 1) {
                    throw new RuntimeException(
                        'The maximum quantity is not configured.'
                    );
                }

                $now = new \DateTimeImmutable();

                if (
                    $event->getStartTime() === null
                    || $event->getEndTime() === null
                    || $now < $event->getStartTime()
                    || $now >= $event->getEndTime()
                ) {
                    throw new RuntimeException(
                    'This flash sale is not currently active.'
                );
                }

                
                $userReservedQuantity = (int) $this->entityManager
                    ->createQuery(
                        'SELECT COALESCE(SUM(r.quantity), 0)
                         FROM App\Entity\InventoryReservation r
                         WHERE r.product = :product
                         AND r.user = :user
                         AND r.flashSaleEvent = :event
                         AND r.status = :status
                         AND r.expiresAt > :now'
                    )
                    ->setParameter('product', $product)
                    ->setParameter('user', $user)
                    ->setParameter('event', $event)
                    ->setParameter('status', 'RESERVED')
                    ->setParameter('now', $now)
                    ->getSingleScalarResult();

            
                $userOrderedQuantity = (int) $this->entityManager
                    ->createQuery(
                        'SELECT COALESCE(SUM(oi.quantity), 0)
                         FROM App\Entity\OrderItems oi
                         JOIN oi.purchaseOrder o
                         WHERE oi.product = :product
                         AND o.user = :user
                         AND o.status IN (:statuses)'
                    )
                    ->setParameter('product', $product)
                    ->setParameter('user', $user)
                    ->setParameter('statuses', ['PENDING', 'PAID'])
                    ->getSingleScalarResult();

                if (
                    $userReservedQuantity
                    + $userOrderedQuantity
                    + $quantity > $maxQuantity
                ) {
                    throw new RuntimeException(
                        'You have reached the maximum quantity for this flash sale.'
                    );
                }

                // Count all active reservations for this product.
                $reservedQuantity = (int) $this->entityManager
                    ->createQuery(
                        'SELECT COALESCE(SUM(r.quantity), 0)
                         FROM App\Entity\InventoryReservation r
                         WHERE r.product = :product
                         AND r.status = :status
                         AND r.expiresAt > :now'
                    )
                    ->setParameter('product', $product)
                    ->setParameter('status', 'RESERVED')
                    ->setParameter('now', $now)
                    ->getSingleScalarResult();

                // Count pending and paid order quantities.
                $orderedQuantity = (int) $this->entityManager
                    ->createQuery(
                        'SELECT COALESCE(SUM(oi.quantity), 0)
                         FROM App\Entity\OrderItems oi
                         JOIN oi.purchaseOrder o
                         WHERE oi.product = :product
                         AND o.status IN (:statuses)'
                    )
                    ->setParameter('product', $product)
                    ->setParameter('statuses', ['PENDING', 'PAID'])
                    ->getSingleScalarResult();

                $availableStock = $product->getInitialStock()
                    - $reservedQuantity
                    - $orderedQuantity;

                if ($quantity > $availableStock) {
                    throw new RuntimeException(
                        'Not enough stock available.'
                    );
                }

                $reservation = new InventoryReservation();
                $reservation->setProduct($product);
                $reservation->setUser($user);
                $reservation->setFlashSaleEvent($event);
                $reservation->setQuantity($quantity);
                $reservation->setStatus('RESERVED');
                $reservation->setExpiresAt(
                    $now->modify('+10 minutes')
                );

                $this->entityManager->persist($reservation);
                $this->entityManager->flush();
                
                return $reservation;
            }
        );
    }
}
