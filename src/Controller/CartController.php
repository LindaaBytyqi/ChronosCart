<?php

namespace App\Controller;

use App\Entity\FlashSaleEvent;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Event\CartItemAddedEvent;
use App\Repository\FlashSaleEventRepository;
use App\Service\CheckoutService;
use App\Service\DynamicPricingService;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route('', name: 'app_cart', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        DynamicPricingService $dynamicPricingService,
        FlashSaleEventRepository $flashSaleEvents
    ): Response {
        [$items, $total] = $this->buildItems(
            $request->getSession()->get('cart', []),
            $entityManager,
            $dynamicPricingService,
            $flashSaleEvents
        );

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        Product $product,
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        StockService $stockService,
        FlashSaleEventRepository $flashSaleEvents
    ): Response {
        if (!$this->isCsrfTokenValid(
            'add_to_cart_' . $product->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $quantity = $request->request->getInt('quantity', 1);

        if ($quantity < 1) {
            $this->addFlash('error', 'Please select a valid quantity.');

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $event = $flashSaleEvents->findOneBy(['product' => $product]);

        if (!$event) {
            $this->addFlash('error', 'This product is not in a flash sale.');

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $secret = (string) $this->getParameter('kernel.secret');
        $ip = (string) $request->getClientIp();
        $ipHash = hash_hmac('sha256', $ip, $secret);
        $identityHash = hash_hmac(
            'sha256',
            $ip . '|' . $request->headers->get('User-Agent') . '|' . $request->headers->get('Accept-Language'),
            $secret
        );

        $user = $this->getUser();

        try {
            $reservation = $stockService->reserve(
                $product->getId(),
                $user instanceof User ? $user->getId() : null,
                $quantity,
                $event,
                $identityHash,
                $ipHash
            );
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $session = $request->getSession();

        $cart = $session->get('cart', []);
        $cart[$product->getId()] = (int) ($cart[$product->getId()] ?? 0) + $quantity;
        $session->set('cart', $cart);

        $tokens = $session->get('cart_tokens', []);
        $tokens[$product->getId()][] = $reservation->getCheckoutToken();
        $session->set('cart_tokens', $tokens);

        $eventDispatcher->dispatch(
            new CartItemAddedEvent($product, $quantity)
        );

        $this->addFlash('success', 'Product added to your cart!');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(
        Product $product,
        Request $request,
        StockService $stockService
    ): Response {
        if (!$this->isCsrfTokenValid(
            'remove_from_cart_' . $product->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $session = $request->getSession();

        $cart = $session->get('cart', []);
        unset($cart[$product->getId()]);
        $session->set('cart', $cart);

        $tokens = $session->get('cart_tokens', []);
        $stockService->release($product->getId(), $tokens[$product->getId()] ?? []);
        unset($tokens[$product->getId()]);
        $session->set('cart_tokens', $tokens);

        $this->addFlash('success', 'Product removed from your cart.');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function checkout(
        Request $request,
        EntityManagerInterface $entityManager,
        DynamicPricingService $dynamicPricingService,
        FlashSaleEventRepository $flashSaleEvents
    ): Response {
        [$items, $subtotal] = $this->buildItems(
            $request->getSession()->get('cart', []),
            $entityManager,
            $dynamicPricingService,
            $flashSaleEvents
        );

        $shipping = 0.0;

        return $this->render('checkout/index.html.twig', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $subtotal + $shipping,
        ]);
    }

    #[Route('/checkout/place-order', name: 'app_checkout_place_order', methods: ['POST'])]
    public function placeOrder(
        Request $request,
        CheckoutService $checkoutService
    ): Response {
        if (!$this->isCsrfTokenValid('checkout', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $session = $request->getSession();
        $map = $session->get('cart_tokens', []);
        $tokens = $map === [] ? [] : array_merge(...array_values($map));

        if ($tokens === []) {
            $this->addFlash('error', 'Your cart is empty or your reservation expired.');

            return $this->redirectToRoute('app_cart');
        }

        $user = $this->getUser();

                try {
            $order = $checkoutService->checkout(
                $tokens,
                $request->request->all(),
                $user instanceof User ? $user : null
            );
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'eservation')) {
                $session->remove('cart');
                $session->remove('cart_tokens');
            }

            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_cart');
        }

        if (!$user instanceof User) {
            $session->set('guest_order_id', $order->getId());
        }

        $session->remove('cart');
        $session->remove('cart_tokens');

        return $this->redirectToRoute('app_order_success', [
            'id' => $order->getId(),
        ]);
    }

    #[Route('/order/{id}/success', name: 'app_order_success', methods: ['GET'])]
    public function orderSuccess(
        Order $order,
        Request $request
    ): Response {
        $user = $this->getUser();
        $guestOrderId = $request->getSession()->get('guest_order_id');

        if ($user instanceof User) {
            if ($order->getUser()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException();
            }
        } elseif ((int) $guestOrderId !== $order->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('checkout/order_success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Builds cart lines with the price the customer will actually pay
     * (dynamic price minus the flash sale discount).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildItems(
        array $cart,
        EntityManagerInterface $entityManager,
        DynamicPricingService $dynamicPricingService,
        FlashSaleEventRepository $flashSaleEvents
    ): array {
        $items = [];
        $total = 0.0;

        foreach ($cart as $productId => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($productId);

            if (!$product || !is_numeric($quantity) || (int) $quantity < 1) {
                continue;
            }

            $quantity = (int) $quantity;
            $event = $flashSaleEvents->findOneBy(['product' => $product]);

            $unitPrice = $this->discountedPrice(
                (float) $dynamicPricingService->getCurrentPrice($product),
                $event
            );

            $subtotal = $unitPrice * $quantity;
            $total += $subtotal;

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return [$items, $total];
    }

    private function discountedPrice(float $price, ?FlashSaleEvent $event): float
    {
        $discount = (float) ($event?->getDiscountPercentage() ?? 0);

        return round($price * (100 - $discount) / 100, 2);
    }
}