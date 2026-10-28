<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderItems;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\LockMode;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route('', name: 'app_cart', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $cart = $request->getSession()->get('cart', []);
        $items = [];
        $total = 0.0;

        foreach ($cart as $productId => $quantity) {
            $product = $entityManager
                ->getRepository(Product::class)
                ->find($productId);

            if (!$product) {
                continue;
            }

            $subtotal = (float) $product->getBasePrice() * $quantity;
            $total += $subtotal;

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        Product $product,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid(
            'add_to_cart_' . $product->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid CSRF token.'
            );
        }

        $quantity = $request->request->getInt('quantity', 1);

        if ($quantity < 1) {
            $this->addFlash('error', 'Please select a valid quantity.');

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $cart = $request->getSession()->get('cart', []);
        $currentQuantity = (int) ($cart[$product->getId()] ?? 0);
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $product->getInitialStock()) {
            $available = max(
                0,
                $product->getInitialStock() - $currentQuantity
            );

            $this->addFlash(
                'error',
                sprintf('Only %d additional unit(s) available.', $available)
            );

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $cart[$product->getId()] = $newQuantity;
        $request->getSession()->set('cart', $cart);

        $this->addFlash('success', 'Product added to your cart!');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(
        Product $product,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid(
            'remove_from_cart_' . $product->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid CSRF token.'
            );
        }

        $cart = $request->getSession()->get('cart', []);
        unset($cart[$product->getId()]);

        $request->getSession()->set('cart', $cart);

        $this->addFlash('success', 'Product removed from your cart.');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function checkout(
        Request $request,
        EntityManagerInterface $entityManager
        ): Response {
    $cart = $request->getSession()->get('cart', []);
    $items = [];
    $subtotal = 0.0;

    foreach ($cart as $productId => $quantity) {
        $product = $entityManager
            ->getRepository(Product::class)
            ->find($productId);

        if (!$product || !is_numeric($quantity) || (int) $quantity < 1) {
            continue;
        }

        $quantity = (int) $quantity;
        $lineTotal = (float) $product->getBasePrice() * $quantity;
        $subtotal += $lineTotal;

        $items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $lineTotal,
        ];
    }

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
    EntityManagerInterface $entityManager,
    ProductRepository $productRepository
): Response {
    if (!$this->isCsrfTokenValid(
        'checkout',
        $request->request->get('_token')
    )) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $session = $request->getSession();
    $cart = $session->get('cart', []);

    if (empty($cart)) {
        $this->addFlash('error', 'Your cart is empty.');
        return $this->redirectToRoute('app_cart');
    }

    $fullName = trim((string) $request->request->get('fullName', ''));
    $email = trim((string) $request->request->get('email', ''));
    $phone = trim((string) $request->request->get('phone', ''));
    $address = trim((string) $request->request->get('address', ''));
    $city = trim((string) $request->request->get('city', ''));
    $zipCode = trim((string) $request->request->get('zipCode', ''));
    $paymentMethod = (string) $request->request->get('paymentMethod', '');

    if (
        mb_strlen($fullName) < 2 ||
        mb_strlen($fullName) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 180 ||
        mb_strlen($phone) < 7 ||
        mb_strlen($phone) > 30 ||
        $address === '' ||
        mb_strlen($address) > 255 ||
        $city === '' ||
        mb_strlen($city) > 100 ||
        $zipCode === '' ||
        mb_strlen($zipCode) > 20 ||
        !in_array($paymentMethod, ['cod', 'online'], true)
    ) {
        $this->addFlash('error', 'Please enter valid checkout information.');
        return $this->redirectToRoute('app_checkout');
    }

    $user = $this->getUser();

    try {
        $order = $entityManager->wrapInTransaction(
            function (EntityManagerInterface $em) use (
                $cart,
                $productRepository,
                $user,
                $fullName,
                $email,
                $phone,
                $address,
                $city,
                $zipCode,
                $paymentMethod
            ): Order {
                $order = new Order();
                $order->setFullName($fullName);
                $order->setEmail($email);
                $order->setPhone($phone);
                $order->setAddress($address);
                $order->setCity($city);
                $order->setZipCode($zipCode);
                $order->setPaymentMethod($paymentMethod);
                $order->setStatus('PENDING');

                $order->setUser($user instanceof User ? $user : null);

                $total = 0.0;

                $cart = array_filter(
                    $cart,
                    static fn ($quantity) =>
                        filter_var($quantity, FILTER_VALIDATE_INT) !== false
                        && (int) $quantity > 0
                );

                ksort($cart, SORT_NUMERIC);

                foreach ($cart as $productId => $quantity) {
                    $quantity = (int) $quantity;

                    $product = $productRepository->findForUpdate(
                        (int) $productId
                    );

                    if (!$product) {
                        throw new \RuntimeException(
                            'A product in your cart is no longer available.'
                        );
                    }

                    if ($quantity > $product->getInitialStock()) {
                        throw new \RuntimeException(
                            sprintf(
                                'Not enough stock for "%s". Please update your cart.',
                                $product->getName()
                            )
                        );
                    }

                    $unitPrice = (float) $product->getBasePrice();
                    $subtotal = $unitPrice * $quantity;
                    $total += $subtotal;

                    $orderItem = new OrderItems();
                    $orderItem->setProduct($product);
                    $orderItem->setQuantity($quantity);
                    $orderItem->setUnitPrice(number_format($unitPrice, 2, '.', ''));
                    $orderItem->setSubtotal(number_format($subtotal, 2, '.', ''));
                    $orderItem->setPurchaseOrder($order);

                    $order->addOrderItem($orderItem);
                    $product->setInitialStock(
                        $product->getInitialStock() - $quantity
                    );

                    $em->persist($orderItem);
                }

                $order->setTotalAmount(number_format($total, 2, '.', ''));
                $em->persist($order);
                $em->flush();

                return $order;
            }
        );
    } catch (\RuntimeException $exception) {
        $this->addFlash('error', $exception->getMessage());
        return $this->redirectToRoute('app_checkout');
    }

    if (!$user instanceof User) {
        $session->set('guest_order_id', $order->getId());
    }

    $session->remove('cart');
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


}
