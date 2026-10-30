<?php

namespace App\Controller;

use App\Service\CheckoutService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    #[Route(
        '/api/checkout',
        name: 'api_checkout',
        methods: ['POST']
    )]
    public function checkout(
        Request $request,
        CheckoutService $checkoutService
    ): JsonResponse {

      
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json([
                'error' => 'Request body must contain valid JSON.',
            ], 400);
        }

       
        $checkoutToken = $data['checkoutToken'] ?? null;

        if (!is_string($checkoutToken) || trim($checkoutToken) === '') {
            return $this->json([
                'error' => 'Checkout token is required.',
            ], 400);
        }

        try {
            $order = $checkoutService->checkout(
                $checkoutToken,
                $data
            );

        } catch (RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], 409);
        }

        return $this->json([
            'message' => 'Order created successfully. Payment is being processed.',
            'orderId' => $order->getId(),
            'totalAmount' => $order->getTotalAmount(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()?->format(
                \DateTimeInterface::ATOM
            ),
        ], 201);
    }
}
