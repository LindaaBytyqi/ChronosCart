<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Entity\FlashSaleEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

    #[Route('/product')]
    final class ProductController extends AbstractController
    {
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $products = $productRepository->findAll();

        $events = $entityManager
            ->getRepository(FlashSaleEvent::class)
            ->findAll();

        $eventsByProductId = [];

    foreach ($events as $event) {
        $product = $event->getProduct();

        if ($product !== null && $product->getId() !== null) {
            $eventsByProductId[$product->getId()] = $event;
        }
    }

    return $this->render('product/index.html.twig', [
        'products' => $products,
        'eventsByProductId' => $eventsByProductId,
    ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = bin2hex(random_bytes(16))
                    . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/uploads/products',
                        $newFilename
                    );

                    $product->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'The image could not be uploaded.');

                    return $this->render('product/new.html.twig', [
                        'product' => $product,
                        'form' => $form,
                    ]);
                }
            }

            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute(
                'app_product_index',
                [],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = bin2hex(random_bytes(16))
                    . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/uploads/products',
                        $newFilename
                    );

                    $product->setImageName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'The image could not be uploaded.');

                    return $this->render('product/edit.html.twig', [
                        'product' => $product,
                        'form' => $form,
                    ]);
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute(
                'app_product_index',
                [],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $product->getId(),
            $request->getPayload()->getString('_token')
        )) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute(
            'app_product_index',
            [],
            Response::HTTP_SEE_OTHER
        );
    }
}
