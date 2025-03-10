<?php

namespace App\Controller;

use App\Entity\Feuillet;
use App\Entity\Paroisse;
use App\Entity\Utilisateur;
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
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\IncrementFeuilletViewMessage;

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
    public function index(Request $request, UserInterface $user): JsonResponse
    {
        // Récupérer la paroisse de l'utilisateur
        $paroisse = $this->entityManager->getRepository(Utilisateur::class)->find($user->getId())->getParoisse();

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        // Vérifier si le paiement est à jour
        if (!$paroisse->isPaiementAJour()) {
            return new JsonResponse(['error' => 'Le paiement de la paroisse n\'est pas à jour.'], 403);
        }
    
        // Récupération des paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1)); // Par défaut 1
        $size = max(1, (int) $request->query->get('size', 5)); // Par défaut 5

        $offset = ($page - 1) * $size;

        // Requête paginée pour les feuillets
        $feuillets = $this->feuilletRepository->findBy(
            ['paroisse' => $paroisse],
            ['id' => 'DESC'],
            $size,
            $offset
        );

        // Calcul du nombre total de feuillets pour cette paroisse
        $totalFeuillets = $this->feuilletRepository->count(['paroisse' => $paroisse]);

        $data = [];
        foreach ($feuillets as $feuillet) {
            $data[] = [
                'id' => $feuillet->getId(),
                'description' => $feuillet->getDescription(),
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d H:i'),
                'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getNom() : null,
                'fileUrl' => $feuillet->getFileUrl(),
                'viewCount' => $feuillet->getViewCount()
            ];
        }

        // Inclure des métadonnées pour la pagination
        $response = [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'page_size' => $size,
                'total_items' => $totalFeuillets,
                'total_pages' => ceil($totalFeuillets / $size),
            ],
        ];

        return new JsonResponse($response);
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
            'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d H:i'),
            'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
            'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            'fileUrl' => $feuillet->getFileUrl(),
            'viewCount' => $feuillet->getViewCount()
        ];

        return new JsonResponse($data);
    }


    #[Route('/api/feuillet', name: 'add_feuillet', methods: ['POST'])]
    public function addFeuillet(Request $request, UserInterface $user): JsonResponse
    {
        if (!array_intersect($user->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $celebrationDate = $request->request->get('celebration_date') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

        if (!isset($celebrationDate) || !isset($paroisseId)) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $file = $request->files->get('feuillet');

        if(!$file){
            return new JsonResponse(['error' => 'Aucun fichier n\'a été transmis.'], 400);
        }

        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Église ou paroisse introuvable.'], 404);
        }

        if (!$paroisse->isPaiementAJour()) {
            return new JsonResponse(['error' => 'Le paiement de la paroisse n\'est pas à jour. Merci de vous rendre dans votre espace paroisse et de régulariser le paiement.'], 403);
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
        if (!array_intersect($user->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet introuvable'], 404);
        }
        
        $celebrationDate = $request->request->get('celebration_date') ?? null;
        $paroisseId = $request->request->get('paroisse_id') ?? null;

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
        if (!array_intersect($user->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

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
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d H:i'),
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
                'celebrationDate' => $feuillet->getCelebrationDate()->format('Y-m-d H:i'),
                'utilisateur' => $feuillet->getUtilisateur() ? $feuillet->getUtilisateur()->getId() : null,
                'paroisse' => $feuillet->getParoisse() ? $feuillet->getParoisse()->getId() : null,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/feuillet/{id}/pdf', name: 'show_feuillet_pdf', methods: ['GET'])]
    public function showFeuilletPdf(int $id, MessageBusInterface $messageBus): Response
    {
        $feuillet = $this->entityManager->getRepository(Feuillet::class)->find($id);

        if (!$feuillet || !$feuillet->getFileUrl()) {
            throw new NotFoundHttpException('Feuillet introuvable ou l\'URL du fichier est manquant.');
        }

        $fileUrl = $feuillet->getFileUrl();

        // Envoyer la tâche d'incrémentation à Messenger
        $messageBus->dispatch(new IncrementFeuilletViewMessage($feuillet->getId()));
        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();

        $client = new Client(['verify'=>false]);
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

    #[Route('/feuillet/paroisse/{paroisseId}/nearest/pdf', name: 'show_nearest_feuillet_pdf_by_paroisse', methods: ['GET'])]
    public function showNearestFeuilletPdfByParoisse(int $paroisseId, MessageBusInterface $messageBus): Response
    {
        $currentDate = new \DateTime();

        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

        if (!$paroisse) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        $feuillet = $this->feuilletRepository->findOneByNearestCelebrationDate($paroisse, $currentDate);

        if (!$feuillet || !$feuillet->getFileUrl()) {
            throw new NotFoundHttpException('Aucun feuillet disponible pour cette paroisse ou l\'URL du fichier est manquante.');
        }

        $fileUrl = $feuillet->getFileUrl();

        $paroisseNom = $paroisse->getNom();
        $celebrationDate = $feuillet->getCelebrationDate()->format('Y-m-d');

        $safeParoisseNom = preg_replace('/[^A-Za-z0-9_-]/', '_', $paroisseNom);
        $fileName = sprintf('%s_%s.pdf', $safeParoisseNom, $celebrationDate);

        $messageBus->dispatch(new IncrementFeuilletViewMessage($feuillet->getId()));

        $client = new Client(['verify' => false]);
        try {
            $response = $client->get($fileUrl);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Fichier introuvable.');
        }

        $pdfContent = $response->getBody()->getContents();

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    #[Route('/api/feuillet/{id}/stats', name: 'get_feuillet_stats', methods: ['GET'])]
    public function getFeuilletStats(int $id): JsonResponse
    {
        $feuillet = $this->feuilletRepository->find($id);

        if (!$feuillet) {
            return new JsonResponse(['error' => 'Feuillet introuvable'], 404);
        }

        $views = $feuillet->getFeuilletViews();

        $data = [];
        foreach ($views as $view) {
            $data[] = [
                'feuillet_id' => $view->getFeuillet()->getId(),
                'paroisse_id' => $view->getParoisse()->getId(),
                'viewed_at' => $view->getViewedAt()->format('Y-m-d H:i')
            ];
        }

        return new JsonResponse($data);
    }

}
