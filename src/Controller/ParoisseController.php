<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Entity\Paroisse;
use App\Repository\ParoisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ParoisseController extends AbstractController
{
    private $entityManager;
    private $paroisseRepository;

    public function __construct(EntityManagerInterface $entityManager, ParoisseRepository $paroisseRepository)
    {
        $this->entityManager = $entityManager;
        $this->paroisseRepository = $paroisseRepository;
    }

    #[Route('/api/paroisse', name: 'get_paroisses', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $paroisses = $this->paroisseRepository->findAll();
        $data = [];

        foreach ($paroisses as $paroisse) {
            $data[] = [
                'id' => $paroisse->getId(),
                'nom' => $paroisse->getNom(),
                'gps' => $paroisse->getGPS(),
                'diocese' => $paroisse->getDiocese() ? $paroisse->getDiocese()->getId() : null,
                'eglises' => array_map(fn($eglise) => $eglise->getId(), $paroisse->getEglises()->toArray()),
                'responsables' => array_map(fn($user) => $user->getId(), $paroisse->getResponsable()->toArray()),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/paroisse', name: 'add_paroisse', methods: ['POST'])]
    public function addParoisse(Request $request, UserInterface $user): JsonResponse
    {
        $nom = $request->request->get('nom') ?? null;
        $gps = $request->request->get('gps') ?? null;
        $dioceseId = $request->request->get('diocese_id') ?? null;

        if (empty($nom) || empty($gps) || empty($dioceseId)) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $diocese = $this->entityManager->getRepository(Diocese::class)->find($dioceseId);

        if (!$diocese) {
            return new JsonResponse(['error' => 'Diocese not found'], 404);
        }

        $paroisse = new Paroisse();
        $paroisse->setNom($nom);
        $paroisse->setGPS($gps);
        $paroisse->setDiocese($diocese);
        $paroisse->addResponsable($user);

        $this->entityManager->persist($paroisse);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Paroisse created'], 201);
    }

    #[Route('/api/paroisse/{id}', name: 'update_paroisse', methods: ['PUT'])]
    public function updateParoisse(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $nom = $data['nom'] ?? null;
        $gps = $data['gps'] ?? null;
        $dioceseId = $data['diocese_id'] ?? null;

        if ($nom) {
            $paroisse->setNom($nom);
        }

        if ($gps) {
            $paroisse->setGPS($gps);
        }

        if ($dioceseId) {
            $diocese = $this->entityManager->getRepository(Diocese::class)->find($dioceseId);
            if ($diocese) {
                $paroisse->setDiocese($diocese);
            }
        }

        $paroisse->addResponsable($user);

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Paroisse updated']);
    }

    #[Route('/api/paroisse/{id}', name: 'delete_paroisse', methods: ['DELETE'])]
    public function deleteParoisse(int $id, UserInterface $user): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse not found'], 404);
        }

        // Optional: Check if the user is a responsible of the paroisse
        if (!$paroisse->getResponsable()->contains($user)) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->entityManager->remove($paroisse);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Paroisse deleted']);
    }
}
