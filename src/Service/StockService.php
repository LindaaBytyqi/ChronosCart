<?php

namespace App\Service;

use App\Entity\FlashSaleEvent;
use App\Entity\InventoryReservation;
use App\Entity\Product;
use App\Entity\User;
use App\Event\InventoryReservedEvent;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StockCacheService $stockCacheService,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function reserve(
        int $productId,
        ?int $userId,
        int $quantity,
        FlashSaleEvent $event,
        ?string $identityHash = null,
        ?string $ipHash = null
    ): InventoryReservation {
        if ($quantity < 1) {
            throw new InvalidArgumentException(
                'Quantity must be at least 1.'
            );
        }

        $lock = $this->lockFactory->createLock(
            'flash_sale_product_' . $productId,
            60.0
        );

        if (!$lock->acquire(true)) {
            throw new RuntimeException(
                'Unable to acquire product stock lock.'
            );
        }

        try {

            $reservation = $this->entityManager->wrapInTransaction(
                function () use (
                    $productId,
                    $userId,
                    $quantity,
                    $event,
                    $identityHash,
                    $ipHash
                ): InventoryReservation {
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

                    $user = null;

                    if ($userId !== null) {
                        $user = $this->entityManager
                            ->getRepository(User::class)
                            ->find($userId);

                        if (!$user instanceof User) {
                            throw new RuntimeException('User not found.');
                        }
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

                    $identityConditions = [];

                    if ($user !== null) {
                        $identityConditions[] = 'r.user = :user';
                    }

                    if ($identityHash !== null) {
                        $identityConditions[] = 'r.identityHash = :identityHash';
                    }

                    if ($identityConditions === []) {
                        throw new RuntimeException(
                            'Unable to identify the customer.'
                        );
                    }


                    $query = $this->entityManager->createQuery(
                        'SELECT COALESCE(SUM(r.quantity), 0)
                         FROM App\Entity\InventoryReservation r
                         WHERE r.product = :product
                         AND r.flashSaleEvent = :event
                         AND (
                             (r.status = :reservedStatus AND r.expiresAt > :now)
                             OR r.status = :convertedStatus
                         )
                         AND (' . implode(' OR ', $identityConditions) . ')'
                    );

                    $query
                        ->setParameter('product', $product)
                        ->setParameter('event', $event)
                        ->setParameter('reservedStatus', 'RESERVED')
                        ->setParameter('convertedStatus', 'CONVERTED')
                        ->setParameter('now', $now);

                    if ($user !== null) {
                        $query->setParameter('user', $user);
                    }

                    if ($identityHash !== null) {
                        $query->setParameter('identityHash', $identityHash);
                    }

                    $alreadyReservedOrPurchased = (int) $query
                        ->getSingleScalarResult();

                    if (
                        $alreadyReservedOrPurchased + $quantity
                        > $maxQuantity
                    ) {
                        throw new RuntimeException(
                            'You have reached the maximum quantity for this flash sale.'
                        );
                    }

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

                    $orderedQuantity = (int) $this->entityManager
                        ->createQuery(
                            'SELECT COALESCE(SUM(oi.quantity), 0)
                             FROM App\Entity\OrderItems oi
                             JOIN oi.purchaseOrder o
                             WHERE oi.product = :product
                             AND o.status IN (:statuses)'
                        )
                        ->setParameter('product', $product)
                        ->setParameter(
                            'statuses',
                            ['PENDING', 'PAID']
                        )
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
                    $reservation->setIdentityHash($identityHash);
                    $reservation->setIpHash($ipHash);
                    $reservation->setCheckoutToken(
                        bin2hex(random_bytes(32))
                    );

                    $this->entityManager->persist($reservation);
                    $this->entityManager->flush();

                    return $reservation;
                }
            );

            $this->stockCacheService->invalidateStock($productId);

            $this->eventDispatcher->dispatch(
                new InventoryReservedEvent($reservation)
            );

            return $reservation;
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns the actual available stock for a product.
     */
    public function getAvailableStock(Product $product): int
    {
        return $this->calculateAvailableStock(
            $product,
            new \DateTimeImmutable()
        );
    }

    /**
     * Calculates available stock using active reservations and orders.
     */
    private function calculateAvailableStock(
        Product $product,
        \DateTimeImmutable $now
    ): int {
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

        return max(
            0,
            (int) $product->getInitialStock()
                - $reservedQuantity
                - $orderedQuantity
        );
    }
}
