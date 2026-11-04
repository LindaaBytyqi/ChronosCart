<?php

namespace App\Controller;

use App\Dto\ReserveRequest;
use App\Entity\FlashSaleEvent;
use App\Entity\User;
use App\Service\AnonymousIdentityService;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        StockService $stockService,
        #[Target('reserve_api')]
        RateLimiterFactory $reserveApiLimiter,
        AnonymousIdentityService $anonymousIdentityService,
        ValidatorInterface $validator,
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

            $clientKey = 'user_' . $userId;
        } else {
            $clientKey = null;
        }

        $identityData = $anonymousIdentityService->getIdentityData($request);

        if ($clientKey === null) {
            $clientKey = 'visitor_' . $identityData['identityHash'];
        }

        $limiter = $reserveApiLimiter->create($clientKey);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $seconds = $retryAfter
                ? max(1, $retryAfter->getTimestamp() - time())
                : 60;

            return $this->json(
                ['error' => 'Too many reservation requests. Try again later.'],
                429,
                ['Retry-After' => (string) $seconds]
            );
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json([
                'error' => 'Request body must contain valid JSON.',
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

        $reserveRequest = new ReserveRequest();
        $reserveRequest->quantity = $data['quantity'] ?? null;
        $reserveRequest->event = $event;
        $reserveRequest->user = $user instanceof User ? $user : null;
        $reserveRequest->identityHash = $identityData['identityHash'];

        $violations = $validator->validate($reserveRequest);

        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->json([
                'error' => 'Validation failed.',
                'violations' => $errors,
            ], 422);
        }

        try {
            $reservation = $stockService->reserve(
                $product->getId(),
                $userId,
                $reserveRequest->quantity,
                $event,
                $identityData['identityHash'],
                $identityData['ipHash']
            );
        } catch (RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], 409);
        }

        $response = $this->json([
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

        $response->headers->setCookie(
            Cookie::create('chronoscart_visitor')
                ->withValue($identityData['token'])
                ->withExpires(new \DateTimeImmutable('+1 year'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}