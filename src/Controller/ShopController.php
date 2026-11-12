<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\FlashSaleEventRepository;
use App\Service\StockCacheService;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function index(
    Request $request,
    ProductRepository $productRepository,
    FlashSaleEventRepository $flashSaleEventRepository,
    StockService $stockService,
    StockCacheService $stockCacheService
    ): Response {

    $products = $productRepository->findActiveProducts();

    $events = $flashSaleEventRepository->findAll();

    $eventsByProductId = [];
    $availableStockByProductId = [];

    foreach ($events as $event) {
        $product = $event->getProduct();

        if ($product?->getId() !== null) {
            $eventsByProductId[$product->getId()] = $event;
        }
    }

    foreach ($products as $product) {
        $productId = $product->getId();

        if ($productId === null) {
            continue;
        }

        $stockFromService = $stockService->getAvailableStock($product);

        $availableStockByProductId[$productId] =
            $stockCacheService->getOrLoadStock(
                $productId,
                fn(): int => $stockFromService
            );
    }

   $response = $this->render('shop/index.html.twig', [
    'products' => $products,
    'eventsByProductId' => $eventsByProductId,
    'availableStockByProductId' => $availableStockByProductId,
    ]);

$cacheData = [];

foreach ($products as $product) {
    $id = $product->getId();

    $event = $eventsByProductId[$id] ?? null;

    $cacheData[] = [
        'id' => $id,
        'name' => $product->getName(),
        'price' => $product->getBasePrice(),
        'stock' => $availableStockByProductId[$id] ?? 0,
        'status' => $product->getStatus(),
        'discount' => $event?->getDiscountPercentage(),
        'start' => $event?->getStartTime()?->format('c'),
        'end' => $event?->getEndTime()?->format('c'),
    ];
}

$response->setEtag(hash('sha256', json_encode($cacheData)));
$response->setPublic();
$response->setMaxAge(10);

if ($response->isNotModified($request)) {
    return $response;
}

return $response;
}

    #[Route('/shop/product/{id}', name: 'app_shop_show', methods: ['GET'])]
    public function show(
        Request $request,
        Product $product,
        FlashSaleEventRepository $flashSaleEventRepository,
        StockService $stockService,
        StockCacheService $stockCacheService
    ): Response {
        $productId = $product->getId();

    if ($productId === null || $product->getStatus() !== 'ACTIVE') {
        throw $this->createNotFoundException('Product not found.');
    }

    $stockFromService = $stockService->getAvailableStock($product);

    $stockFromCache = $stockCacheService->getOrLoadStock(
        $productId,
        fn(): int => $stockFromService
    );

    $availableStock = $stockFromCache;

    $flashSaleEvent = $flashSaleEventRepository->findOneBy([
        'product' => $product
    ]);

    $response = $this->render('shop/show.html.twig', [
    'product' => $product,
    'availableStock' => $availableStock,
    'flashSaleEvent' => $flashSaleEvent,
    'stockFromService' => $stockFromService,
]);

$cacheData = [
    'id' => $product->getId(),
    'name' => $product->getName(),
    'price' => $product->getBasePrice(),
    'stock' => $availableStock,
    'status' => $product->getStatus(),
    'discount' => $flashSaleEvent?->getDiscountPercentage(),
    'start' => $flashSaleEvent?->getStartTime()?->format('c'),
    'end' => $flashSaleEvent?->getEndTime()?->format('c'),
];

$response->setEtag(hash('sha256', json_encode($cacheData)));
$response->setPublic();
$response->setMaxAge(10);

if ($response->isNotModified($request)) {
    return $response;
}

return $response;
}

}
