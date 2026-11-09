<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\FlashSaleEventRepository;
use App\Service\StockCacheService;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function index(
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

    return $this->render('shop/index.html.twig', [
        'products' => $products,
        'eventsByProductId' => $eventsByProductId,
        'availableStockByProductId' => $availableStockByProductId,
    ]);
    }

    #[Route('/shop/product/{id}', name: 'app_shop_show', methods: ['GET'])]
    public function show(
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

    return $this->render('shop/show.html.twig', [
        'product' => $product,
        'availableStock' => $availableStock,
        'flashSaleEvent' => $flashSaleEvent,
        'stockFromService' => $stockFromService,
    ]);
    }

}
