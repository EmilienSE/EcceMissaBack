<?php

namespace App\Controller;

use App\Entity\Eglise;
use App\Entity\Feuillet;
use App\Entity\Paroisse;
use App\Repository\FeuilletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class FeuilletController extends AbstractController
{
    private $entityManager;
    private $feuilletRepository;

    public function __construct(EntityManagerInterface $entityManager, FeuilletRepository $feuilletRepository)
    {
        $this->entityManager = $entityManager;
        $this->feuilletRepository = $feuilletRepository;
    }

    #[Route('/api/feuillet', name: 'get_feuillet', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $feuillets = $this->feuilletRepository->findAll();
        $data = [];

        foreach ($feuillets as $feuillet) {
            $data[] = [
                'id' => $feuillet->getId(),
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d'),
                'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
                'eglise' => $feuillet->getEglise() ? $feuillet->getEglise()->getId() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/feuillet', name: 'add_feuillet', methods: ['POST'])]
    public function addFeuillet(Request $request, UserInterface $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $egliseId = $data['eglise_id'] ?? null;
        $celebrationDate = $data['celebration_date'] ?? null;
        $paroisseId = $data['paroisse_id'] ?? null;

        if (empty($egliseId) || empty($celebrationDate) || empty($paroisseId)) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $eglise = $this->entityManager->getRepository(Eglise::class)->find($egliseId);
        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$eglise || !$paroisse) {
            return new JsonResponse(['error' => 'Church or Parish not found'], 404);
        }

        $feuillet = new Feuillet();
        $feuillet->setUtilisateur($user);
        $feuillet->setEglise($eglise);
        $feuillet->setParoisse($paroisse);
        $feuillet->setCelebrationDate(new \DateTime($celebrationDate));

        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet created'], 201);
    }

    #[Route('/api/feuillet/{id}', name: 'update_feuillet', methods: ['PUT'])]
    public function updateFeuillet(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $egliseId = $data['eglise_id'] ?? null;
        $celebrationDate = $data['celebration_date'] ?? null;
        $paroisseId = $data['paroisse_id'] ?? null;

        if ($egliseId) {
            $eglise = $this->entityManager->getRepository(Eglise::class)->find($egliseId);
            if ($eglise) {
                $feuillet->setEglise($eglise);
            }
        }

        if ($paroisseId) {
            $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);
            if ($paroisse) {
                $feuillet->setParoisse($paroisse);
            }
        }

        if ($celebrationDate) {
            $feuillet->setCelebrationDate(new \DateTime($celebrationDate));
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet updated']);
    }

    #[Route('/api/feuillet/{id}', name: 'delete_feuillet', methods: ['DELETE'])]
    public function deleteFeuillet(int $id, UserInterface $user): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet not found'], 404);
        }

        if ($feuillet->getUtilisateur() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $this->entityManager->remove($feuillet);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet deleted']);
    }

    #[Route('/api/feuillet/user/latest', name: 'get_user_latest_feuillets', methods: ['GET'])]
    public function getUserLatestFeuillets(UserInterface $user): JsonResponse
    {
        $feuillets = $this->feuilletRepository->findBy(
            ['utilisateur' => $user],
            ['id' => 'DESC'],
            5
        );

        $data = [];
        foreach ($feuillets as $feuillet) {
            $data[] = [
                'id' => $feuillet->getId(),
                'description' => $feuillet->getDescription(),
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d'),
                'eglise' => $feuillet->getEglise() ? $feuillet->getEglise()->getId() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/feuillet/paroisse/{paroisseId}/latest', name: 'get_paroisse_latest_feuillets', methods: ['GET'])]
    public function getParoisseLatestFeuillets(int $paroisseId): JsonResponse
    {
        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Parish not found'], 404);
        }

        $feuillets = $this->feuilletRepository->findBy(
            ['paroisse' => $paroisse],
            ['id' => 'DESC'],
            5
        );

        $data = [];
        foreach ($feuillets as $feuillet) {
            $data[] = [
                'id' => $feuillet->getId(),
                'description' => $feuillet->getDescription(),
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d'),
                'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
                'eglise' => $feuillet->getEglise() ? $feuillet->getEglise()->getId() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            ];
        }

        return new JsonResponse($data);
    }
}
