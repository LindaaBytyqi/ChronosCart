<?php

namespace App\Service;

use App\Entity\InventoryReservation;
use App\Entity\Order;
use App\Entity\FlashSaleEvent;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\OrderItems;
use App\Message\ProcessPaymentMessage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use App\Service\DynamicPricingService;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly DynamicPricingService $dynamicPricingService,
        private readonly StockCacheService $stockCacheService,
        
    ) {
    }

    public function checkout(array $checkoutTokens, array $data, ?User $user): Order
    {
    $tokens = array_values(array_unique(array_filter($checkoutTokens, 'is_string')));
    sort($tokens);

    if ($tokens === []) {
        throw new RuntimeException('Your cart is empty.');
    }

    $fullName = $this->requiredString($data, 'fullName');
    $email    = $this->requiredString($data, 'email');
    $phone    = $this->requiredString($data, 'phone');
    $address  = $this->requiredString($data, 'address');
    $city     = $this->requiredString($data, 'city');
    $zipCode  = $this->requiredString($data, 'zipCode');
    $payment  = $this->requiredString($data, 'paymentMethod');

    if (!in_array($payment, ['cod', 'online'], true)) {
        throw new RuntimeException('Invalid payment method.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email address.');
    }

    $productIds = [];

    $order = $this->entityManager->wrapInTransaction(
        function () use ($tokens, $user, $fullName, $email, $phone, $address, $city, $zipCode, $payment, &$productIds): Order {
            $order = new Order();
            $order->setUser($user);
            $order->setFullName($fullName);
            $order->setEmail($email);
            $order->setPhone($phone);
            $order->setAddress($address);
            $order->setCity($city);
            $order->setZipCode($zipCode);
            $order->setPaymentMethod($payment);
            $order->setStatus('PENDING');

            $totalCents = 0;
            $now = new \DateTimeImmutable();

            foreach ($tokens as $token) {
                $r = $this->entityManager
                    ->getRepository(InventoryReservation::class)
                    ->createQueryBuilder('r')
                    ->where('r.checkoutToken = :t')
                    ->setParameter('t', $token)
                    ->getQuery()
                    ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                    ->getOneOrNullResult();

                if (!$r) {
                    throw new RuntimeException('Reservation not found.');
                }
                if ($r->getStatus() !== 'RESERVED') {
                    throw new RuntimeException('A reservation is no longer available.');
                }
                if ($r->getExpiresAt() <= $now) {
                    throw new RuntimeException('A reservation has expired. Please add the product again.');
                }
                if ($r->getUser()?->getId() !== $user?->getId()) {
                    throw new RuntimeException('Reservation does not belong to you.');
                }

                $product = $r->getProduct();
                $event   = $r->getFlashSaleEvent();

                
                if ($product->getStatus() !== 'ACTIVE') {
                    throw new RuntimeException('Product is not active.');
                }

                $unit = $this->unitPriceCents($product, $event);
                $line = $unit * $r->getQuantity();
                $totalCents += $line;

                $item = new OrderItems();
                $item->setProduct($product);
                $item->setQuantity($r->getQuantity());
                $item->setUnitPrice($this->formatCents($unit));
                $item->setSubtotal($this->formatCents($line));

                $order->addOrderItem($item);
                $this->entityManager->persist($item);

                $r->setStatus('CONVERTED');
                $r->setPurchaseOrder($order);
                $productIds[] = $product->getId();
            }

            $order->setTotalAmount($this->formatCents($totalCents));
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $order;
        }
    );

    foreach (array_unique($productIds) as $id) {
        $this->stockCacheService->invalidateStock($id);
    }

    if ($order->getPaymentMethod() === 'online') {
        $this->messageBus->dispatch(new ProcessPaymentMessage($order->getId()));
    }

    return $order;
}

private function unitPriceCents(Product $product, FlashSaleEvent $event): int
{
    $base = $this->toCents($this->dynamicPricingService->getCurrentPrice($product));
    $bps  = $this->toCents($event->getDiscountPercentage() ?? '0');

    if ($bps < 0 || $bps > 10000) {
        throw new RuntimeException('Invalid discount percentage.');
    }

    return intdiv($base * (10000 - $bps) + 5000, 10000);
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
