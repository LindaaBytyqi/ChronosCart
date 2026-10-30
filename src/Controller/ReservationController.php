<?php

namespace App\Controller;

use App\Entity\FlashSaleEvent;
use App\Entity\User;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    #[Route(
        '/api/flash-sales/{id}/reserve',
        name: 'api_flash_sale_reserve',
        methods: ['POST']
    )]
    public function reserve(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        StockService $stockService
    ): JsonResponse {

        $user = $this->getUser();

        $userId = null;

        if ($user instanceof User) {
            $userId = $user->getId();

        if ($userId === null) {
            return $this->json([
             'error' => 'Invalid user account.',
            ], 401);
        }
        }

       
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json([
                'error' => 'Request body must contain valid JSON.',
            ], 400);
        }

        $quantity = $data['quantity'] ?? null;

        if (!is_int($quantity) || $quantity < 1) {
            return $this->json([
                'error' => 'Quantity must be a positive integer.',
            ], 400);
        }

       
        $event = $entityManager
            ->getRepository(FlashSaleEvent::class)
            ->find($id);

        if (!$event instanceof FlashSaleEvent) {
            return $this->json([
                'error' => 'Flash sale event not found.',
            ], 404);
        }

  
        $now = new \DateTimeImmutable();

        if (
            $event->getStartTime() === null
            || $event->getEndTime() === null
            || $now < $event->getStartTime()
            || $now >= $event->getEndTime()
        ) {
            return $this->json([
                'error' => 'This flash sale is not currently active.',
            ], 409);
        }

        $product = $event->getProduct();

        if ($product === null || $product->getId() === null) {
            return $this->json([
                'error' => 'No valid product is linked to this event.',
            ], 409);
        }

        try {
            $reservation = $stockService->reserve(
                $product->getId(),
                $userId,
                $quantity,
                $event
            );
        } catch (RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], 409);
        }

        return $this->json([
            'message' => 'Product reserved successfully.',
            'reservationId' => $reservation->getId(),
            'flashSaleEventId' => $event->getId(),
            'productId' => $product->getId(),
            'quantity' => $reservation->getQuantity(),
            'status' => $reservation->getStatus(),
            'checkoutToken' => $reservation->getCheckoutToken(),
            'expiresAt' => $reservation->getExpiresAt()?->format(
                \DateTimeInterface::ATOM
            ),
        ], 201);
    }
}
