<?php

namespace App\Controller;

use App\Entity\Eglise;
use App\Entity\Feuillet;
use App\Entity\Paroisse;
use Aws\S3\S3Client;
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
                'fileUrl' => $feuillet->getFileUrl()
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/feuillet', name: 'add_feuillet', methods: ['POST'])]
    public function addFeuillet(Request $request, UserInterface $user): JsonResponse
    {
        $egliseId = $request->request->get('eglise_id') ?? null;
        $celebrationDate = $request->request->get('celebration_date') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if (!isset($egliseId) || !isset($celebrationDate) || !isset($paroisseId)) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $file = $request->files->get('feuillet');

        if(!$file){
            return new JsonResponse(['error' => 'File not provided'], 400);
        }

        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Church or Parish not found'], 404);
        }
        
        // Configuration du client S3
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['S3_REGION'],
            'credentials' => [
                'key' => $_ENV['S3_KEY'],
                'secret' => $_ENV['S3_SECRET'],
            ],
            'endpoint' => 'https://s3.' . $_ENV['S3_REGION'] . '.io.cloud.ovh.net', // Assurez-vous de définir le bon endpoint OVH
        ]);

        // Génération d'un nom de fichier unique pour éviter les conflits
        $filename = uniqid() . '.' . $file->guessExtension();

        // Téléchargement du fichier sur S3
        try {
            $result = $s3Client->putObject([
                'Bucket' => $_ENV['S3_BUCKET'],
                'Key' => $filename,
                'SourceFile' => $file->getPathname(),
                'ACL' => 'public-read', // Si vous voulez que le fichier soit public
            ]);

            $fileUrl = $result['ObjectURL']; // Récupération de l'URL du fichier
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }

        $feuillet = new Feuillet();
        $feuillet->setUtilisateur($user);
        $feuillet->setParoisse($paroisse);
        $feuillet->setDescription('');
        $feuillet->setCelebrationDate(new \DateTime($celebrationDate));
        $feuillet->setFileUrl($fileUrl); // Enregistrement de l'URL du fichier dans la base de données

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
