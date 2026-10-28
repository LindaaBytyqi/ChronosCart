<?php

namespace App\MessageHandler;

use App\Message\ProcessOrderMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessOrderMessageHandler{
    public function __invoke(ProcessOrderMessage $message): void
    {
        // do something with your message
    }
}
