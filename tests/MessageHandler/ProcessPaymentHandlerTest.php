<?php

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Message\ProcessPaymentMessage;
use App\MessageHandler\ProcessPaymentHandler;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProcessPaymentHandlerTest extends TestCase
{
    private function createHandler(?Order $order, bool $simulateFailure = false, int $flushCalls = 0): ProcessPaymentHandler
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->method('wrapInTransaction')
            ->willReturnCallback(fn (callable $callback) => $callback($em));

        $em->method('find')
            ->with(Order::class, 1, LockMode::PESSIMISTIC_WRITE)
            ->willReturn($order);

        $em->expects($this->exactly($flushCalls))->method('flush');

        return new ProcessPaymentHandler($em, $simulateFailure);
    }

    private function createOrder(string $status, string $paymentMethod): Order
    {
        $order = new Order();
        $order->setStatus($status);
        $order->setPaymentMethod($paymentMethod);

        return $order;
    }

    public function testPorosiaPending_Online_BehetPaid(): void
    {
        $order = $this->createOrder('PENDING', 'online');

        $handler = $this->createHandler($order, false, 1);
        $handler(new ProcessPaymentMessage(1));

        $this->assertSame('PAID', $order->getStatus());
    }

    public function testPorosiaMeGjendjeTjeterNukNdryshon(): void
    {
        $order = $this->createOrder('PAID', 'online');

        $handler = $this->createHandler($order, false, 0);
        $handler(new ProcessPaymentMessage(1));

        $this->assertSame('PAID', $order->getStatus());
    }

    public function testPagesaJoOnlineNukProcesohet(): void
    {
        $order = $this->createOrder('PENDING', 'cash');

        $handler = $this->createHandler($order, false, 0);
        $handler(new ProcessPaymentMessage(1));

        $this->assertSame('PENDING', $order->getStatus());
    }

    public function testPorosiaQeNukEkzistonHedhGabim(): void
    {
        $handler = $this->createHandler(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order not found.');

        $handler(new ProcessPaymentMessage(1));
    }

    public function testDeshtimiISimuluarHedhGabimDheStatusiMbetetPending(): void
    {
        $order = $this->createOrder('PENDING', 'online');

        $handler = $this->createHandler($order, true, 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated payment gateway failure.');

        try {
            $handler(new ProcessPaymentMessage(1));
        } finally {
            $this->assertSame('PENDING', $order->getStatus());
        }
    }
}