<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Entity\Paroisse;
use App\Entity\Utilisateur;
use App\Repository\ParoisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Stripe\Stripe;
use Stripe\Customer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        // Vérifier si l'utilisateur est déjà responsable d'une paroisse
        $existingParoisse = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['id' => $user->getId()])->getParoisse();

        if ($existingParoisse) {
            return new JsonResponse(['error' => 'Vous êtes déjà responsable d\'une paroisse.'], 403);
        }

        $nom = $request->request->get('nom') ?? null;
        $gps = 'x';//$request->request->get('gps') ?? null;
        $dioceseId = $request->request->get('diocese_id') ?? null;

        if (empty($nom) || empty($gps) || empty($dioceseId)) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $diocese = $this->entityManager->getRepository(Diocese::class)->find($dioceseId);

        if (!$diocese) {
            return new JsonResponse(['error' => 'Diocèse introuvable'], 404);
        }

        $paroisse = new Paroisse();
        $paroisse->setNom($nom);
        $paroisse->setGPS($gps);
        $paroisse->setDiocese($diocese);
        $paroisse->addResponsable($user);
        $paroisse->setPaiementAJour(false);

        // Générer un code unique aléatoire
        $codeUnique = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $paroisse->setCodeUnique($codeUnique);

        $this->entityManager->persist($paroisse);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Paroisse créée', 'paroisse_id' => $paroisse->getId()], 201);
    }

    #[Route('/api/paroisse/{id}', name: 'delete_paroisse', methods: ['DELETE'])]
    public function deleteParoisse(int $id, UserInterface $user): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        // Check if the user is a responsible of the paroisse
        if (!$paroisse->getResponsable()->contains($user)) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        foreach($paroisse->getResponsable() as $responsable) {
            $paroisse->removeResponsable($responsable);
        }

        foreach($paroisse->getEglises() as $eglise) {
            $paroisse->removeEglise($eglise);
        }

        // Appel à la fonction d'annulation de l'abonnement Stripe
        $error = $this->cancelStripeSubscription($paroisse);
        if ($error) {
            return new JsonResponse(['error' => $error], 500);
        }
        
        $this->entityManager->remove($paroisse);
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Paroisse supprimée']);
    }

    #[Route('/api/paroisse/paymentIntent', name: 'create_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $paroisseId = $request->request->get('paroisse_id') ?? null;
        $paroisse = $this->paroisseRepository->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        // Configure Stripe API key
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Création d'un client Stripe uniquement si la paroisse n'en a pas déjà un
            if (is_null($paroisse->getStripeCustomerId())) {
                $customer = Customer::create([
                    'description' => 'Client pour la paroisse '.$paroisse->getNom()
                ]);
                $paroisse->setStripeCustomerId($customer->id);
                $this->entityManager->persist($paroisse);
                $this->entityManager->flush();
            }

            $session = Session::create([
                'success_url' => 'http://localhost:4200/paroisse', // /success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://localhost:4200/paroisse/fail',
                'mode' => 'subscription',
                'payment_method_types' => ['card', 'sepa_debit'],
                'line_items' => [[
                    'price' => 'price_1Q7XV3E8TPrVnm48J30MCsnl',
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'trial_period_days' => 14
                ],
                'metadata' => [
                    'paroisse_id' => $paroisse->getId(), // ID de la paroisse ajouté ici
                ],
                'subscription_data'=> [
                    'metadata' => [
                        'paroisse_id' => $paroisse->getId()
                    ]
                ]
            ]);
            
            // Répondre avec l'ID de la session pour le frontend
            return new JsonResponse([
                'paymentLink' => $session->url,
            ], 201);

        } catch (\Exception $e) {
            // Gérer les erreurs et retourner une réponse adéquate
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/paroisse/join', name: 'join_paroisse', methods: ['POST'])]
    public function joinParoisse(Request $request, UserInterface $user): JsonResponse
    {
        // Vérifier si l'utilisateur est déjà responsable d'une paroisse
        $existingParoisse = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['id' => $user->getId()])->getParoisse();

        if ($existingParoisse) {
            return new JsonResponse(['error' => 'Vous êtes déjà responsable d\'une paroisse.'], 403);
        }

        $codeUnique = $request->request->get('code_unique') ?? null;

        if (empty($codeUnique)) {
            return new JsonResponse(['error' => 'Code unique manquant'], 400);
        }

        // Rechercher la paroisse avec le code unique
        $paroisse = $this->paroisseRepository->findOneBy(['codeUnique' => $codeUnique]);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable avec ce code'], 404);
        }

        // Ajouter l'utilisateur à la paroisse
        $paroisse->addResponsable($user);

        $this->entityManager->persist($paroisse);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Utilisateur ajouté à la paroisse'], 200);
    }

    #[Route('/api/paroisse/leave', name: 'leave_paroisse', methods: ['POST'])]
    public function leaveParoisse(Request $request, UserInterface $user): JsonResponse
    {
        // Rechercher la paroisse de l'utilisateur
        $paroisse = $this->entityManager->getRepository(Utilisateur::class)->find($user)->getParoisse();

        if (!$paroisse) {
            return new JsonResponse(['error' => 'L\'utilisateur n\'appartient à aucune paroisse'], 404);
        }

        // Vérifier si l'utilisateur est responsable de la paroisse
        if (!$paroisse->getResponsable()->contains($user)) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas responsable de cette paroisse'], 403);
        }

        // Supprimer l'utilisateur de la liste des responsables
        $paroisse->removeResponsable($user);

        // Vérifier si la paroisse n'a plus de responsables
        if ($paroisse->getResponsable()->isEmpty()) {
            // Appel à la fonction d'annulation de l'abonnement Stripe
            $error = $this->cancelStripeSubscription($paroisse);
            if ($error) {
                return new JsonResponse(['error' => $error], 500);
            }

            // Supprimer la paroisse si elle n'a plus de responsables
            $this->entityManager->remove($paroisse);
        }

        // Sauvegarder les changements
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Vous avez quitté la paroisse'], 200);
    }

    #[Route('/api/paroisse/ma_paroisse', name: 'get_user_paroisse', methods: ['GET'])]
    public function getUserParoisse(UserInterface $user): JsonResponse
    {
        
        $paroisse = $this->entityManager->getRepository(Utilisateur::class)->find($user)->getParoisse();

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        $responsables = [];
        foreach($paroisse->getResponsable() as $responsable) {
            $responsables[] = $responsable->getEmail();
        }

        if($paroisse){
            return new JsonResponse([
                'id' => $paroisse->getId(),
                'nom' => $paroisse->getNom(),
                'gps' => $paroisse->getGPS(),
                'diocese' => $paroisse->getDiocese() ? $paroisse->getDiocese()->getNom() : null,
                'diocese_id' => $paroisse->getDiocese() ? $paroisse->getDiocese()->getId() : null,
                'paiement_a_jour' => $paroisse->isPaiementAJour(),
                'code_unique' => $paroisse->getCodeUnique(),
                'responsables' => $responsables
            ]);
        } else {
            return new JsonResponse('null', 200, [], true);
        }
    }

    #[Route('/api/paroisse/{id}', name: 'update_paroisse', methods: ['POST'])]
    public function updateParoisse(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        if (!$paroisse->getResponsable()->contains($user)) {
            return new JsonResponse(['error' => 'Non autorisé. Vous n\'êtes pas responsable de cette paroisse.'], 403);
        }

        $nom = $request->request->get('nom') ?? null;
        $gps = $request->request->get('gps') ?? null;
        $dioceseId = $request->request->get('diocese_id') ?? null;

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
            } else {
                return new JsonResponse(['error' => 'Diocèse introuvable'], 404);
            }
        }

        if (!$paroisse->getResponsable()->contains($user)) {
            $paroisse->addResponsable($user);
        }

        $this->entityManager->persist($paroisse);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Paroisse mise à jour']);
    }

    #[Route('/api/paroisse/{id}/retry_payment', name: 'retry_payment', methods: ['POST'])]
    public function retryPayment(int $id): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        if ($paroisse->isPaiementAJour()) {
            return new JsonResponse(['message' => 'Le paiement est déjà à jour'], 400);
        }

        // Configurer la clé API Stripe
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Création d'une nouvelle session de paiement pour régulariser
            $session = Session::create([
                'success_url' => 'http://localhost:4200/paroisse',  // Rediriger vers une page de succès
                'cancel_url' => 'http://localhost:4200/paroisse',     // Rediriger en cas d'échec
                'mode' => 'subscription',
                'payment_method_types' => ['card', 'sepa_debit'],
                'line_items' => [[
                    'price' => 'price_1Q7XV3E8TPrVnm48J30MCsnl',
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'metadata' => [
                        'paroisse_id' => $paroisse->getId(),
                    ]
                ]
            ]);

            // Retourner le lien de paiement pour permettre au frontend de rediriger l'utilisateur
            return new JsonResponse([
                'paymentLink' => $session->url,
            ], 201);

        } catch (\Exception $e) {
            // Gérer les erreurs Stripe
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/paroisse/{id}/billing_portal', name: 'billing_portal', methods: ['GET'])]
    public function redirectToBillingPortal(int $id): JsonResponse
    {
        // Rechercher la paroisse
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        // Vérifier si la paroisse a un client Stripe associé
        if (!$paroisse->getStripeCustomerId()) {
            return new JsonResponse(['error' => 'Aucun client Stripe trouvé pour cette paroisse'], 404);
        }

        // Configurer la clé API Stripe
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Créer une session du portail de facturation Stripe pour le client
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $paroisse->getStripeCustomerId(),
                'return_url' => 'http://localhost:4200/paroisse',  // URL vers laquelle rediriger l'utilisateur après la gestion de son abonnement
            ]);

            // Retourner le lien de redirection vers le portail de facturation
            return new JsonResponse([
                'billingPortalLink' => $session->url,
            ], 200);

        } catch (\Exception $e) {
            // Gérer les erreurs Stripe
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/paroisse/{id}/pdf', name: 'generate_paroisse_pdf', methods: ['GET'])]
    public function generateParoissePdf(int $id): Response
    {
        $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($id);

        if (!$paroisse) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        // URL vers la route showNearestFeuilletPdfByParoisse
        $routeUrl = $this->generateUrl(
            'show_nearest_feuillet_pdf_by_paroisse',
            ['paroisseId' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Génération du PDF
        $pdf = new \TCPDF();
        $oswald = \TCPDF_FONTS::addTTFfont('Oswald.ttf', 'TrueTypeUnicode', '', 96);
        $oswaldB = \TCPDF_FONTS::addTTFfont('Oswald-Bold.ttf', 'TrueTypeUnicode', '', 96);
        $roboto = \TCPDF_FONTS::addTTFfont('Roboto-Bold.ttf', 'TrueTypeUnicode', '', 96);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Ecce Missa');
        $pdf->SetTitle('Suivez la messe depuis votre téléphone');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();

        // Contenu du PDF
        $logo = 'logo.png'; // Remplacez par le chemin de votre logo
        $pdf->Image($logo, 95, 10, 20, 20, '', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Ajouter le titre principal
        $pdf->SetFont($oswald, '', 20);
        $pdf->SetTextColor(38, 65, 66);
        $pdf->Ln(20); // Saut de ligne
        $pdf->Cell(0, 10, $paroisse->getNom(), 0, 1, 'C');
        $pdf->Ln(15);
        $pdf->SetFont($oswaldB, 'B', 45);
        $pdf->Cell(0, 10, 'Suivez la messe depuis', 0, 1, 'C');
        $pdf->Cell(0, 10, 'votre téléphone', 0, 1, 'C');

        // Générer le QR Code
        $qrCodeUrl = $routeUrl; // Remplacez par l'URL de votre QR code
        $style = [
            'border' => 0,
            'padding' => 4,
            'fgcolor' => [38, 65, 66],
            'bgcolor' => [255, 255, 255],
        ];
        $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 72.5, 105, 70, 70, $style, 'N');

        // Ajouter le texte explicatif
        $pdf->Ln(20);
        $pdf->SetFont($roboto, '', 30);
        $pdf->Cell(0, 10, 'Scannez le QR Code, et voilà !', 0, 1, 'C');

        // Ajouter le pied de page
        $pdf->SetFont($oswald, '', 10);
        $pdf->SetY(-30);
        $pdf->Cell(0, 10, 'Proposé par ECCE MISSA', 0, 0, 'L');
        $pdf->Cell(0, 10, 'https://eccemissa.fr/', 0, 0, 'R');

        // Envoi du PDF en réponse
        return new Response($pdf->Output('paroisse_qr_code.pdf', 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function cancelStripeSubscription(Paroisse $paroisse): ?string
    {
        $stripeSubscriptionId = $paroisse->getStripeSubscriptionId();
        
        if ($stripeSubscriptionId) {
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            
            try {
                // Récupérer et annuler l'abonnement Stripe
                $subscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                $subscription->cancel();
                
                // Mettre à jour l'état de l'abonnement dans la base de données
                $paroisse->setPaiementAJour(false);
                $this->entityManager->flush();
                
                return null; // Succès
            } catch (\Exception $e) {
                return 'Erreur lors de l\'annulation de l\'abonnement Stripe: ' . $e->getMessage();
            }
        }

        return null; // Pas d'abonnement à annuler
    }

}
