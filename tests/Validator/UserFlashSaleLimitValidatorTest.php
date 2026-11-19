<?php

namespace App\Tests\Validator;

use App\DTO\ReserveRequest;
use App\Entity\FlashSaleEvent;
use App\Entity\Product;
use App\Validator\UserFlashSaleLimit;
use App\Validator\UserFlashSaleLimitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UserFlashSaleLimitValidatorTest extends ConstraintValidatorTestCase
{
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        parent::setUp();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new UserFlashSaleLimitValidator($this->em);
    }

    private function mockAlreadyReserved(int $already): void
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn((string) $already);
        $this->em->method('createQuery')->willReturn($query);
    }

    private function request(int $quantity, ?string $identityHash = 'hash', ?string $ipHash = null): ReserveRequest
    {
        $product = (new Product())
            ->setSku('X')->setName('X')->setBasePrice('10.00')
            ->setInitialStock(5)->setStatus('ACTIVE');

        $event = (new FlashSaleEvent())
            ->setProduct($product)
            ->setMaxQuantityPerUser(2);

        $r = new ReserveRequest();
        $r->event = $event;
        $r->quantity = $quantity;
        $r->identityHash = $identityHash;
        $r->ipHash = $ipHash;

        return $r;
    }

    public function testWithinLimitIsValid(): void
    {
        $this->mockAlreadyReserved(1);
        $this->validator->validate($this->request(1), new UserFlashSaleLimit());
        $this->assertNoViolation();
    }

    public function testExceedingLimitAddsViolation(): void
    {
        $constraint = new UserFlashSaleLimit();
        $this->mockAlreadyReserved(2);
        $this->validator->validate($this->request(1), $constraint);

        $this->buildViolation($constraint->message)
            ->atPath('property.path.quantity')
            ->assertRaised();
    }

    public function testIpHashAloneIsEnoughToIdentifyAndEnforceLimit(): void
    {
        $constraint = new UserFlashSaleLimit();
        $this->mockAlreadyReserved(2);
        $this->validator->validate($this->request(1, null, 'ip-hash'), $constraint);

        $this->buildViolation($constraint->message)
            ->atPath('property.path.quantity')
            ->assertRaised();
    }

    public function testNoIdentityAddsViolation(): void
    {
        $this->validator->validate($this->request(1, null, null), new UserFlashSaleLimit());

        $this->buildViolation('Unable to identify the customer.')
            ->atPath('property.path.quantity')
            ->assertRaised();
    }

    public function testInvalidQuantityIsIgnored(): void
    {
        $this->validator->validate($this->request(0), new UserFlashSaleLimit());
        $this->assertNoViolation();
    }
}



