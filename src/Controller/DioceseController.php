<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Repository\DioceseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DioceseController extends AbstractController
{
    private $entityManager;
    private $dioceseRepository;

    public function __construct(EntityManagerInterface $entityManager, DioceseRepository $dioceseRepository)
    {
        $this->entityManager = $entityManager;
        $this->dioceseRepository = $dioceseRepository;
    }

    #[Route('/api/diocese', name: 'get_dioceses', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $dioceses = $this->dioceseRepository->findAll();
        $data = [];

        foreach ($dioceses as $diocese) {
            $data[] = [
                'id' => $diocese->getId(),
                'nom' => $diocese->getNom(),
                'responsable' => $diocese->getResponsable() ? $diocese->getResponsable()->getId() : null,
                'paroisses' => array_map(fn($paroisse) => $paroisse->getId(), $diocese->getParoisses()->toArray()),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/diocese', name: 'add_diocese', methods: ['POST'])]
    public function addDiocese(Request $request, UserInterface $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $nom = $data['nom'] ?? null;

        if (empty($nom)) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $diocese = new Diocese();
        $diocese->setNom($nom);
        $diocese->setResponsable($user);

        $this->entityManager->persist($diocese);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Diocese created'], 201);
    }

    #[Route('/api/diocese/{id}', name: 'update_diocese', methods: ['PUT'])]
    public function updateDiocese(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $diocese = $this->dioceseRepository->find($id);

        if (!$diocese) {
            return new JsonResponse(['error' => 'Diocese not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $nom = $data['nom'] ?? null;

        if ($nom) {
            $diocese->setNom($nom);
        }

        // Optional: Only allow the responsible user to update
        if ($diocese->getResponsable() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Diocese updated']);
    }

    #[Route('/api/diocese/{id}', name: 'delete_diocese', methods: ['DELETE'])]
    public function deleteDiocese(int $id, UserInterface $user): JsonResponse
    {
        $diocese = $this->dioceseRepository->find($id);

        if (!$diocese) {
            return new JsonResponse(['error' => 'Diocese not found'], 404);
        }

        // Optional: Only allow the responsible user to delete
        if ($diocese->getResponsable() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->entityManager->remove($diocese);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Diocese deleted']);
    }
}
