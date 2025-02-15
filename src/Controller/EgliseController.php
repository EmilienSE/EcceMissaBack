<?php

namespace App\Controller;

use App\Entity\Eglise;
use App\Entity\Paroisse;
use App\Repository\EgliseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class EgliseController extends AbstractController
{
    private $entityManager;
    private $egliseRepository;

    public function __construct(EntityManagerInterface $entityManager, EgliseRepository $egliseRepository)
    {
        $this->entityManager = $entityManager;
        $this->egliseRepository = $egliseRepository;
    }

    #[Route('/api/eglise', name: 'get_eglises', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $eglises = $this->egliseRepository->findAll();
        $data = [];

        foreach ($eglises as $eglise) {
            $data[] = [
                'id' => $eglise->getId(),
                'nom' => $eglise->getNom(),
                'gps' => $eglise->getGPS(),
                'paroisse' => $eglise->getParoisse() ? $eglise->getParoisse()->getId() : null,
                'responsables' => array_map(fn($user) => $user->getId(), $eglise->getResponsable()->toArray()),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/eglise', name: 'add_eglise', methods: ['POST'])]
    public function addEglise(Request $request, UserInterface $user): JsonResponse
    {
        $nom = $request->request->get('nom') ?? null;
        $gps = $request->request->get('gps') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if (empty($nom) || empty($gps) || empty($paroisseId)) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse inexistante'], 404);
        }

        // Vérifier le nombre d'églises associées à la paroisse
        $nombreEglises = $this->entityManager->getRepository(Eglise::class)->count(['paroisse' => $paroisse]);

        if ($nombreEglises >= 3) {
            return new JsonResponse(['error' => 'Les paroisses ne peuvent pas avoir plus de trois églises'], 400);
        }

        $eglise = new Eglise();
        $eglise->setNom($nom);
        $eglise->setGPS($gps);
        $eglise->setParoisse($paroisse);
        $eglise->addResponsable($user);

        $this->entityManager->persist($eglise);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Église crée'], 201);
    }

    #[Route('/api/eglise/{id}', name: 'update_eglise', methods: ['POST'])]
    public function updateEglise(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $eglise = $this->egliseRepository->find($id);

        if (!$eglise) {
            return new JsonResponse(['error' => 'Église introuvable'], 404);
        }

        $nom = $request->request->get('nom') ?? null;
        $gps = $request->request->get('gps') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if ($nom) {
            $eglise->setNom($nom);
        }

        if ($gps) {
            $eglise->setGPS($gps);
        }

        if ($paroisseId) {
            $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);
            if ($paroisse) {
                $eglise->setParoisse($paroisse);
            }
        }

        $eglise->addResponsable($user);

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Église mise à jour']);
    }

    #[Route('/api/eglise/{id}', name: 'delete_eglise', methods: ['DELETE'])]
    public function deleteEglise(int $id, UserInterface $user): JsonResponse
    {
        $eglise = $this->egliseRepository->find($id);

        if (!$eglise) {
            return new JsonResponse(['error' => 'Église introuvable'], 404);
        }

        // Check if the user is a responsible of the eglise
        if (!$eglise->getResponsable()->contains($user)) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $this->entityManager->remove($eglise);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Église supprimée']);
    }
}
