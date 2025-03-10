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
use App\Entity\Utilisateur;

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
        $nom = $request->request->get('nom') ?? null;

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

        $nom = $request->request->get('nom') ?? null;

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

        // Only allow the responsible user to delete
        if ($diocese->getResponsable() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->entityManager->remove($diocese);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Diocese deleted']);
    }

    #[Route('/api/diocese/mon_diocese', name: 'get_user_diocese', methods: ['GET'])]
    public function getUserDiocese(UserInterface $user): JsonResponse
    {
        $paroisse = $this->entityManager->getRepository(Utilisateur::class)->find($user)->getParoisse();

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }
        
        $diocese = $paroisse->getDiocese();

        if (!$diocese) {
            return new JsonResponse(['error' => 'Diocèse introuvable'], 404);
        }

        $paroisses = [];
        foreach($diocese->getParoisses() as $paroisse) {
            $paroisses[] = $paroisse->getNom();
        }

        if($diocese){
            return new JsonResponse([
                'id' => $diocese->getId(),
                'nom' => $diocese->getNom(),
                'paroisses' => $paroisses,
            ]);
        } else {
            return new JsonResponse('null', 200, [], true);
        }
    }
}
