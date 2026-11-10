<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(
    dirname(__DIR__, 2) . '/.env'
);

use App\Entity\Product;
use App\Entity\FlashSaleEvent;
use App\Kernel;

$kernel = new Kernel('load_test', false);
$kernel->boot();

$entityManager = $kernel->getContainer()
    ->get('doctrine')
    ->getManager();

$product = new Product();
$product
    ->setSku('LOAD-TEST-' . time())
    ->setName('ChronosCart Load Test Product')
    ->setBasePrice('100.00')
    ->setInitialStock(1)
    ->setStatus('ACTIVE');

$event = new FlashSaleEvent();
$event
    ->setProduct($product)
    ->setStartTime(new DateTimeImmutable('-5 minutes'))
    ->setEndTime(new DateTimeImmutable('+1 day'))
    ->setMaxQuantityPerUser(1)
    ->setDiscountPercentage('10.00');

$entityManager->persist($event);
$entityManager->flush();

echo "Product ID: " . $product->getId() . PHP_EOL;
echo "Flash Sale Event ID: " . $event->getId() . PHP_EOL;

$kernel->shutdown();
