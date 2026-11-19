<?php

namespace App\Tests\Messenger;

use App\Message\ProcessPaymentMessage;
use App\Message\RestockInventoryMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

class PaymentLifecycleTest extends KernelTestCase
{
    public function testPaymentFailsRetriesThenDlqAndRestock(): void
    {
        self::bootKernel();
        $c = static::getContainer();

        $bus = $c->get(MessageBusInterface::class);
        $dispatcher = $c->get('event_dispatcher');

    
        $servicesResetter = $c->get('services_resetter');

        $registry = $c->get('doctrine');

        $dispatcher->addListener(
            \Symfony\Component\Messenger\Event\WorkerMessageFailedEvent::class,
            function () use ($registry): void {
                $registry->resetManager();
            },
            -1024
        );
            

        /** @var InMemoryTransport $async */
        $async = $c->get('messenger.transport.async');
        /** @var InMemoryTransport $failed */
        $failed = $c->get('messenger.transport.failed');

        // Porosia nuk ekziston handler-i hedh RuntimeException çdo her
        $bus->dispatch(new ProcessPaymentMessage(999999999));

        $worker = new Worker(['async' => $async], $bus, $dispatcher);
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $e) use ($worker) {
            if ($e->isWorkerIdle()) {
                $worker->stop();
            }
        });
        $worker->run(['sleep' => 0]);

        $sent = $async->getSent();

        $payments = array_filter($sent, fn ($e) => $e->getMessage() instanceof ProcessPaymentMessage);
        $restocks = array_filter($sent, fn ($e) => $e->getMessage() instanceof RestockInventoryMessage);

        foreach ($sent as $e) {
            fwrite(STDERR, sprintf(
                "%s retry=%d\n",
                get_class($e->getMessage()),
                \Symfony\Component\Messenger\Stamp\RedeliveryStamp::getRetryCountFromEnvelope($e)
            ));
        }
        fwrite(STDERR, 'DLQ=' . count($failed->getSent()) . "\n");

        // 1 dërgim fillestar + 3 retry
        $this->assertCount(4, $payments);

        // Pas dështimit përfundimtar dërgohet RestockInventoryMessage
        $this->assertCount(1, $restocks);

        // Mesazhi i pagesës përfundon në DLQ (failed transport)
        $dlq = $failed->getSent();
        $this->assertCount(1, $dlq);
        $this->assertInstanceOf(ProcessPaymentMessage::class, array_values($dlq)[0]->getMessage());
    }
}

