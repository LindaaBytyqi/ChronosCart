<?php

namespace App\Tests\Validator;

use App\DTO\ReserveRequest;
use App\Entity\FlashSaleEvent;
use App\Entity\Product;
use App\Entity\User;
use App\Validator\UserFlashSaleLimit;
use App\Validator\UserFlashSaleLimitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserFlashSaleLimitValidatorTest extends ConstraintValidatorTestCase
{
    private EntityManagerInterface $entityManager;

    private int $alreadyReserved = 0;

   protected function createValidator(): UserFlashSaleLimitValidator
    {
    $query = $this->createMock(Query::class);

    $query->method('setParameter')
        ->willReturnSelf();

    $query->method('getSingleScalarResult')
        ->willReturnCallback(fn () => $this->alreadyReserved);

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);

    $entityManager
        ->expects($this->any())
        ->method('createQuery')
        ->willReturn($query);

    $this->entityManager = $entityManager;

    return new UserFlashSaleLimitValidator($this->entityManager);
    }

    private function createReserveRequest(
        int $quantity,
        bool $withIdentity = true
    ): ReserveRequest {
        $product = new Product();

        $event = (new FlashSaleEvent())
            ->setProduct($product)
            ->setMaxQuantityPerUser(3);

        $request = new ReserveRequest();
        $request->quantity = $quantity;
        $request->event = $event;

        if ($withIdentity) {
            $request->identityHash = 'visitor-hash-123';
        }

        return $request;
    }

    public function testReservationIsAllowedWithinLimit(): void
    {
        $this->alreadyReserved = 1;

        $request = $this->createReserveRequest(1);

        $this->validator->validate(
            $request,
            new UserFlashSaleLimit()
        );

        $this->assertNoViolation();
    }

    public function testReservationIsRejectedWhenLimitIsExceeded(): void
    {
        $this->alreadyReserved = 3;

        $request = $this->createReserveRequest(1);

        $this->validator->validate(
            $request,
            new UserFlashSaleLimit()
        );

        $this->buildViolation(
            'You have reached the maximum quantity for this flash sale.'
        )
            ->atPath('property.path.quantity')
            ->assertRaised();
    }

    public function testReservationIsRejectedWhenQuantityExceedsRemainingLimit(): void
    {
        $this->alreadyReserved = 2;

        $request = $this->createReserveRequest(2);

        $this->validator->validate(
            $request,
            new UserFlashSaleLimit()
        );

        $this->buildViolation(
            'You have reached the maximum quantity for this flash sale.'
        )
            ->atPath('property.path.quantity')
            ->assertRaised();
    }

    public function testReservationIsRejectedWithoutCustomerIdentity(): void
    {
        $request = $this->createReserveRequest(1, false);

        $this->validator->validate(
            $request,
            new UserFlashSaleLimit()
        );

        $this->buildViolation(
            'Unable to identify the customer.'
        )
            ->atPath('property.path.quantity')
            ->assertRaised();
    }   
}
