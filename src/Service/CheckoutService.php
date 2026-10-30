<?php

namespace App\Service;

use App\Entity\InventoryReservation;
use App\Entity\Order;
use App\Entity\OrderItems;
use App\Message\ProcessPaymentMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function checkout(string $checkoutToken, array $data): Order
    {
        if ($checkoutToken === '') {
            throw new RuntimeException('Checkout token is required.');
        }

        return $this->entityManager->wrapInTransaction(
            function () use ($checkoutToken, $data): Order {

                $reservation = $this->entityManager
                    ->getRepository(InventoryReservation::class)
                    ->createQueryBuilder('r')
                    ->where('r.checkoutToken = :token')
                    ->setParameter('token', $checkoutToken)
                    ->getQuery()
                    ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                    ->getOneOrNullResult();

                if (!$reservation) {
                    throw new RuntimeException('Reservation not found.');
                }


                if ($reservation->getStatus() !== 'RESERVED') {
                    throw new RuntimeException(
                        'This reservation is no longer available.'
                    );
                }

                if ($reservation->getExpiresAt() <= new \DateTimeImmutable()) {
                    throw new RuntimeException('Reservation has expired.');
                }

                $product = $reservation->getProduct();
                $event = $reservation->getFlashSaleEvent();
                $quantity = $reservation->getQuantity();

                if (!$product || !$event || !$quantity || $quantity < 1) {
                    throw new RuntimeException('Invalid reservation data.');
                }

                if ($product->getStatus() !== 'ACTIVE') {
                    throw new RuntimeException('Product is not active.');
                }

                $fullName = $this->requiredString($data, 'fullName');
                $email = $this->requiredString($data, 'email');
                $phone = $this->requiredString($data, 'phone');
                $address = $this->requiredString($data, 'address');
                $city = $this->requiredString($data, 'city');
                $zipCode = $this->requiredString($data, 'zipCode');
                $paymentMethod = $this->requiredString($data, 'paymentMethod');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Invalid email address.');

                }

                $basePriceCents = $this->toCents($product->getBasePrice());
                $discountBps = $this->toCents(
                    $event->getDiscountPercentage() ?? '0'
                );

                if ($discountBps < 0 || $discountBps > 10000) {
                    throw new RuntimeException('Invalid discount percentage.');
                }

                $unitPriceCents = intdiv(
                    ($basePriceCents * (10000 - $discountBps)) + 5000,
                    10000
                );

                $subtotalCents = $unitPriceCents * $quantity;
                $order = new Order();

                $order->setUser($reservation->getUser());
                $order->setFullName($fullName);
                $order->setEmail($email);
                $order->setPhone($phone);
                $order->setAddress($address);
                $order->setCity($city);
                $order->setZipCode($zipCode);
                $order->setPaymentMethod($paymentMethod);
                $order->setTotalAmount($this->formatCents($subtotalCents));
                $order->setStatus('PENDING');

                $this->entityManager->persist($order);

                $orderItem = new OrderItems();

                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice(
                    $this->formatCents($unitPriceCents)
                );
                $orderItem->setSubtotal(
                    $this->formatCents($subtotalCents)
                );

                $order->addOrderItem($orderItem);

                $this->entityManager->persist($orderItem);

                $reservation->setStatus('CONVERTED');

                $this->entityManager->flush();

                $orderId = $order->getId();

                if ($orderId === null) {
                    throw new RuntimeException('Order could not be created.');
                }

                $this->messageBus->dispatch(
                    new ProcessPaymentMessage($orderId)
                );

                return $order;
            }
        );
    }

    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                sprintf('The field "%s" is required.', $field)
            );
        }

        return trim($value);
    }

    private function toCents(?string $amount): int
    {
        if (
            $amount === null ||
            !preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)
        ) {
            throw new RuntimeException('Invalid monetary value.');
        }

        [$whole, $decimal] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($decimal, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
