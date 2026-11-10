<?php

use App\Entity\FlashSaleEvent;
use App\Service\StockService;
use App\Kernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(
    dirname(__DIR__, 2) . '/.env'
);

$productId = (int) ($argv[1] ?? 0);
$eventId = (int) ($argv[2] ?? 0);
$identityHash = $argv[3] ?? '';

$kernel = new Kernel(
    $_SERVER['APP_ENV'] ?? 'test',
    false
);

try {
    $kernel->boot();

    $container = $kernel->getContainer()->get('test.service_container');

    $entityManager = $container->get('doctrine')->getManager();

    $event = $entityManager
        ->getRepository(FlashSaleEvent::class)
        ->find($eventId);

    if (!$event) {
        throw new RuntimeException('Flash sale event not found.');
    }

    $stockService = $container->get(StockService::class);

    $stockService->reserve(
        $productId,
        null,
        1,
        $event,
        $identityHash,
        null
    );

    echo "SUCCESS\n";
} catch (RuntimeException $exception) {
    echo "REJECTED\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage());
    exit(2);
} finally {
    $kernel->shutdown();
}





