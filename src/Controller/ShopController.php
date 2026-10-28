<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\FlashSaleEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        FlashSaleEventRepository $flashSaleEventRepository
    ): Response {
        $products = $productRepository->findAll();
        $events = $flashSaleEventRepository->findAll();

        $eventsByProductId = [];

        foreach ($events as $event) {
            $product = $event->getProduct();

            if ($product !== null && $product->getId() !== null) {
                $eventsByProductId[$product->getId()] = $event;
            }
        }

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'eventsByProductId' => $eventsByProductId,
        ]);
    }

    #[Route('/shop/product/{id}', name: 'app_shop_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('shop/show.html.twig', [
            'product' => $product,
        ]);
    }
}
