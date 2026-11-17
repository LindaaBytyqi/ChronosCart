<?php

namespace App\Tests\Message;

use App\Message\ProcessPaymentMessage;
use App\Message\RestockInventoryMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class MessengerRoutingTest extends KernelTestCase
{
    private function getTransport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');

        return $transport;
    }

    public function testProcessPaymentMessageDergohetNeAsync(): void
    {
        self::bootKernel();

        $bus = static::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new ProcessPaymentMessage(1));

        $sent = $this->getTransport()->getSent();

        $this->assertCount(1, $sent);
        $this->assertInstanceOf(ProcessPaymentMessage::class, $sent[0]->getMessage());
    }

    public function testRestockInventoryMessageDergohetNeAsync(): void
    {
        self::bootKernel();

        $bus = static::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new RestockInventoryMessage(1));

        $sent = $this->getTransport()->getSent();

        $this->assertCount(1, $sent);
        $this->assertInstanceOf(RestockInventoryMessage::class, $sent[0]->getMessage());
    }
}