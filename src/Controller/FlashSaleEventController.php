<?php

namespace App\Controller;

use App\Entity\FlashSaleEvent;
use App\Form\FlashSaleEventType;
use App\Repository\FlashSaleEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/flash/sale/event')]
final class FlashSaleEventController extends AbstractController
{
    #[Route('/', name: 'app_flash_sale_event_index', methods: ['GET'])]
    public function index(FlashSaleEventRepository $flashSaleEventRepository): Response
    {
        return $this->render('flash_sale_event/index.html.twig', [
            'flashSaleEvents' => $flashSaleEventRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_flash_sale_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $flashSaleEvent = new FlashSaleEvent();
        $form = $this->createForm(FlashSaleEventType::class, $flashSaleEvent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($flashSaleEvent);
            $entityManager->flush();

            return $this->redirectToRoute('app_flash_sale_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('flash_sale_event/new.html.twig', [
            'flash_sale_event' => $flashSaleEvent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_flash_sale_event_show', methods: ['GET'])]
    public function show(FlashSaleEvent $flashSaleEvent): Response
    {
        return $this->render('flash_sale_event/show.html.twig', [
            'flashSaleEvent' => $flashSaleEvent,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_flash_sale_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FlashSaleEvent $flashSaleEvent, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FlashSaleEventType::class, $flashSaleEvent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_flash_sale_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('flash_sale_event/edit.html.twig', [
            'flashSaleEvent' => $flashSaleEvent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_flash_sale_event_delete', methods: ['POST'])]
    public function delete(Request $request, FlashSaleEvent $flashSaleEvent, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$flashSaleEvent->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($flashSaleEvent);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_flash_sale_event_index', [], Response::HTTP_SEE_OTHER);
    }
}
