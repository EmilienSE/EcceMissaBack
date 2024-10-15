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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Client;

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
                'egliseName' => $feuillet->getEglise() ? $feuillet->getEglise()->getNom() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
                'fileUrl' => $feuillet->getFileUrl(),
                'viewCount' => $feuillet->getViewCount()
            ];
        }
        
        usort($data, function ($a, $b) {
            return strtotime($b['celebrationDate']) - strtotime($a['celebrationDate']);
        });

        return new JsonResponse($data);
    }

    #[Route('/api/feuillet/{id}', name: 'get_feuillet_by_id', methods: ['GET'])]
    public function getFeuilletById(int $id): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet introuvable'], 404);
        }

        $data = [
            'id' => $feuillet->getId(),
            'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d'),
            'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
            'eglise' => $feuillet->getEglise() ? $feuillet->getEglise()->getId() : null,
            'egliseName' => $feuillet->getEglise() ? $feuillet->getEglise()->getNom() : null,
            'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            'fileUrl' => $feuillet->getFileUrl(),
            'viewCount' => $feuillet->getViewCount()
        ];

        return new JsonResponse($data);
    }


    #[Route('/api/feuillet', name: 'add_feuillet', methods: ['POST'])]
    public function addFeuillet(Request $request, UserInterface $user): JsonResponse
    {
        $egliseId = $request->request->get('eglise_id') ?? null;
        $celebrationDate = $request->request->get('celebration_date') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if (!isset($egliseId) || !isset($celebrationDate) || !isset($paroisseId)) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $file = $request->files->get('feuillet');

        if(!$file){
            return new JsonResponse(['error' => 'Aucun fichier n\'a été transmis.'], 400);
        }

        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);
        $eglise = $this->entityManager->getRepository(Eglise::class)->find($egliseId);

        if (!$paroisse || !$eglise) {
            return new JsonResponse(['error' => 'Église ou paroisse introuvable.'], 404);
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
            return new JsonResponse(['error' => 'Erreur d\'upload du fichier: ' . $e->getMessage()], 500);
        }

        $feuillet = new Feuillet();
        $feuillet->setUtilisateur($user);
        $feuillet->setEglise($eglise);
        $feuillet->setParoisse($paroisse);
        $feuillet->setDescription('');
        $feuillet->setCelebrationDate(new \DateTime($celebrationDate));
        $feuillet->setFileUrl($fileUrl); // Enregistrement de l'URL du fichier dans la base de données

        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet créé'], 201);
    }

    #[Route('/api/feuillet/{id}', name: 'update_feuillet', methods: ['POST'])]
    public function updateFeuillet(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet introuvable'], 404);
        }
        
        $egliseId = $request->request->get('eglise_id') ?? null;
        $celebrationDate = $request->request->get('celebration_date') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if ($egliseId && !is_null($egliseId)) {
            $eglise = $this->entityManager->getRepository(Eglise::class)->find($egliseId);
            if ($eglise) {
                $feuillet->setEglise($eglise);
            } else {
                return new JsonResponse(['error' => 'Église introuvable'], 404);
            }
        }

        if ($paroisseId && !is_null($paroisseId)) {
            $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);
            if ($paroisse) {
                $feuillet->setParoisse($paroisse);
            } else {
                return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
            }
        } 

        if ($celebrationDate) {
            $feuillet->setCelebrationDate(new \DateTime($celebrationDate));
        }

        $file = $request->files->get('feuillet');

        if($file){
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

            // Suppression de l'ancien fichier du stockage S3
            try {
                $oldFileUrl = $feuillet->getFileUrl();
                if ($oldFileUrl) {
                    $oldFilename = basename(parse_url($oldFileUrl, PHP_URL_PATH));
                    $s3Client->deleteObject([
                        'Bucket' => $_ENV['S3_BUCKET'],
                        'Key' => $oldFilename,
                    ]);
                }
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'La suppression de l\'ancien fichier a échouée: ' . $e->getMessage()], 500);
            }

            // Génération d'un nom de fichier unique pour éviter les conflits
            $filename = uniqid() . '.' . $file->guessExtension();

            // Téléchargement du nouveau fichier sur S3
            try {
                $result = $s3Client->putObject([
                    'Bucket' => $_ENV['S3_BUCKET'],
                    'Key' => $filename,
                    'SourceFile' => $file->getPathname(),
                    'ACL' => 'public-read', // Si vous voulez que le fichier soit public
                ]);

                $fileUrl = $result['ObjectURL']; // Récupération de l'URL du fichier
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Erreur d\'upload du fichier: ' . $e->getMessage()], 500);
            }
            
            $feuillet->setFileUrl($fileUrl); // Enregistrement de l'URL du fichier dans la base de données
        }

        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet mis à jour']);
    }

    #[Route('/api/feuillet/{id}', name: 'delete_feuillet', methods: ['DELETE'])]
    public function deleteFeuillet(int $id, UserInterface $user): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet introuvable'], 404);
        }

        if ($feuillet->getUtilisateur() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
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

        // Extraction du nom du fichier à partir de l'URL
        $fileUrl = $feuillet->getFileUrl();
        $filename = basename(parse_url($fileUrl, PHP_URL_PATH));

        // Suppression du fichier du stockage S3
        try {
            $s3Client->deleteObject([
                'Bucket' => $_ENV['S3_BUCKET'],
                'Key' => $filename,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur de suppression du fichier: ' . $e->getMessage()], 500);
        }

        // Suppression de l'objet Feuillet de la base de données
        $this->entityManager->remove($feuillet);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Feuillet supprimé']);
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
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
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

    #[Route('/feuillet/{id}/pdf', name: 'show_feuillet_pdf', methods: ['GET'])]
    public function showFeuilletPdf(int $id): Response
    {
        $feuillet = $this->entityManager->getRepository(Feuillet::class)->find($id);

        if (!$feuillet || !$feuillet->getFileUrl()) {
            throw new NotFoundHttpException('Feuillet introuvable ou l\'URL du fichier est manquant.');
        }

        $fileUrl = $feuillet->getFileUrl();

        // Incrémenter le compteur de vues
        $feuillet->incrementViewCount();
        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();

        $client = new Client();
        try {
            $response = $client->get($fileUrl);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $pdfContent = $response->getBody()->getContents();

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="feuillet.pdf"',
        ]);
    }
}
