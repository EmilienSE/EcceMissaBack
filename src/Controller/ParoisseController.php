<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Entity\Paroisse;
use App\Entity\Utilisateur;
use App\Repository\ParoisseRepository;
use App\Service\EmailService;
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
use Stripe\BillingPortal\Session as BillingSession;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\FeuilletView;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\RoundBlockSizeMode;

class ParoisseController extends AbstractController
{
    private $entityManager;
    private $paroisseRepository;
    private $emailService;

    public function __construct(EntityManagerInterface $entityManager, ParoisseRepository $paroisseRepository, EmailService $emailService)
    {
        $this->entityManager = $entityManager;
        $this->paroisseRepository = $paroisseRepository;
        $this->emailService = $emailService;
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
        // Vérifier si acceptCgvCgu est envoyé à true
        $acceptCgvCgu = $request->request->get('acceptCgvCgu') ?? null;
        if ($acceptCgvCgu !== 'true') {
            return new JsonResponse(['error' => 'Les CGV et CGU doivent être acceptées.'], 400);
        }

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
        $user->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']); // Assign 'ROLE_ADMIN' and 'ROLE_EDITOR' to the user
        $paroisse->setPaiementAJour(false);
        $paroisse->setCguAccepted(true);
        $paroisse->setCgvAccepted(true);

        // Générer un code unique aléatoire
        $codeUnique = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $paroisse->setCodeUnique($codeUnique);

        $this->emailService->sendParoisseAjouteeEmail($nom, $user->getPrenom(), $user->getNom(), $user->getEmail());

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

        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        foreach($paroisse->getResponsable() as $responsable) {
            $this->emailService->sendParoisseSupprimeeEmail($paroisse->getNom(), $responsable->getPrenom(), $responsable->getNom(), $responsable->getEmail());
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
        $price = $request->request->get('price') ?? null;
        $paroisse = $this->paroisseRepository->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }
        if (!$price || !in_array($price, ['monthly', 'quarterly', 'yearly'])) {
            return new JsonResponse(['error' => 'Tarif incorrect ou manquant'], 400);
        }

        $price_id = null;
        switch ($price) {
            case 'monthly':
                $price_id = $_ENV['STRIPE_MONTHLY_PRICE'];
                break;
            case 'quarterly':
                $price_id = $_ENV['STRIPE_QUARTERLY_PRICE'];
                break;
            case 'yearly':
                $price_id = $_ENV['STRIPE_YEARLY_PRICE'];
                break;
            default:
                return new JsonResponse(['error' => 'Tarif incorrect'], 400);
        }

        // Configure Stripe API key
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Création d'un client Stripe uniquement si la paroisse n'en a pas déjà un
            if (is_null($paroisse->getStripeCustomerId())) {
                $customer = Customer::create([
                    'description' => $paroisse->getNom().' - '.$paroisse->getDiocese()->getNom()
                ]);
                $paroisse->setStripeCustomerId($customer->id);
                $this->entityManager->persist($paroisse);
                $this->entityManager->flush();
            }

            $session = Session::create([
                'success_url' => 'https://app.eccemissa.fr/paroisse', // /success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'https://app.eccemissa.fr/paroisse/fail',
                'customer' => $customer->id,
                'mode' => 'subscription',
                'payment_method_types' => ['card', 'sepa_debit'],
                'line_items' => [[
                    'price' => $price_id,
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
                ],
                "allow_promotion_codes" => true
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

        foreach($paroisse->getResponsable() as $responsable) {
            $this->emailService->sendNouvelUtilisateurParoisseEmail($paroisse->getNom(), $responsable->getPrenom(), $responsable->getNom(), $responsable->getEmail(), $user->getNom(), $user->getPrenom());
        }

        // Ajouter l'utilisateur à la paroisse
        $paroisse->addResponsable($user);
        $user->setRoles(array_unique(array_merge($user->getRoles(), ['ROLE_USER'])));

        $this->entityManager->persist($paroisse);
        $this->entityManager->flush();

        $this->emailService->sendParoisseRejointeEmail($user->getNom(), $user->getPrenom(), $paroisse->getNom(), $user->getEmail());

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

        // Génération du QR Code
        $routeUrl = $this->generateUrl(
            'show_nearest_feuillet_pdf_by_paroisse',
            ['paroisseId' => $paroisse->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $qrCode = new QrCode(
            data: $routeUrl,
            encoding: new Encoding('UTF-8'),
            size: 300,
            foregroundColor: new Color(38, 65, 66),
            backgroundColor: new Color(255, 255, 255, 127),
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Enlarge
        );

        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);

        // Conversion en Base64
        $qrCodeBase64 = base64_encode($qrCodeResult->getString());

        if($paroisse){
            return new JsonResponse([
                'id' => $paroisse->getId(),
                'nom' => $paroisse->getNom(),
                'gps' => $paroisse->getGPS(),
                'diocese' => $paroisse->getDiocese() ? $paroisse->getDiocese()->getNom() : null,
                'diocese_id' => $paroisse->getDiocese() ? $paroisse->getDiocese()->getId() : null,
                'paiement_a_jour' => $paroisse->isPaiementAJour(),
                'code_unique' => $paroisse->getCodeUnique(),
                'responsables' => $responsables,
                'qr_code' => $qrCodeBase64
            ]);
        } else {
            return new JsonResponse('null', 200, [], true);
        }
    }

    #[Route('/api/paroisse/retry_payment', name: 'retry_payment', methods: ['POST'])]
    public function retryPayment(Request $request): JsonResponse
    {
        $paroisseId = $request->request->get('paroisse_id') ?? null;
        $price = $request->request->get('price') ?? null;
        $paroisse = $this->paroisseRepository->find($paroisseId);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }
        if (!$price || !in_array($price, ['monthly', 'quarterly', 'yearly'])) {
            return new JsonResponse(['error' => 'Tarif incorrect ou manquant'], 400);
        }

        $price_id = null;
        switch ($price) {
            case 'monthly':
                $price_id = $_ENV['STRIPE_MONTHLY_PRICE'];
                break;
            case 'quarterly':
                $price_id = $_ENV['STRIPE_QUARTERLY_PRICE'];
                break;
            case 'yearly':
                $price_id = $_ENV['STRIPE_YEARLY_PRICE'];
                break;
            default:
                return new JsonResponse(['error' => 'Tarif incorrect'], 400);
        }

        // Configure Stripe API key
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Création d'un client Stripe uniquement si la paroisse n'en a pas déjà un
            if (is_null($paroisse->getStripeCustomerId())) {
                $customer = Customer::create([
                    'description' => $paroisse->getNom().' - '.$paroisse->getDiocese()->getNom()
                ]);
                $paroisse->setStripeCustomerId($customer->id);
                $this->entityManager->persist($paroisse);
                $this->entityManager->flush();
            } else {
                $customer = Customer::retrieve($paroisse->getStripeCustomerId());
            }

            $session = Session::create([
                'success_url' => 'https://app.eccemissa.fr/paroisse', // /success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'https://app.eccemissa.fr/paroisse/fail',
                'customer' => $customer->id,
                'mode' => 'subscription',
                'payment_method_types' => ['card', 'sepa_debit'],
                'line_items' => [[
                    'price' => $price_id,
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
                ],
                "allow_promotion_codes" => true
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

    #[Route('/api/paroisse/{id}', name: 'update_paroisse', methods: ['POST'])]
    public function updateParoisse(Request $request, int $id, UserInterface $user): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
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
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        try {
            // Créer une session du portail de facturation Stripe pour le client
            $session = BillingSession::create([
                'customer' => $paroisse->getStripeCustomerId(),
                'return_url' => 'https://app.eccemissa.fr/paroisse',  // URL vers laquelle rediriger l'utilisateur après la gestion de son abonnement
            ]);

            // Retourner le lien de redirection vers le portail de facturation
            return new JsonResponse([
                'customer' => $paroisse->getStripeCustomerId(),
                'billingPortalLink' => $session->url,
            ], 200);

        } catch (\Exception $e) {
            // Gérer les erreurs Stripe
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/paroisse/{id}/pdf/{format}', name: 'generate_paroisse_pdf', methods: ['GET'])]
    public function generateParoissePdf(int $id, string $format = 'A5'): Response
    {
        // Récupération de la paroisse
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

        // Définition du format de la page
        $pageSizes = [
            'A4' => 'A4',
            'A3' => 'A3',
            'A5' => 'A5',
            'bookmark' => [50, 150],  // Marque-page (format personnalisé)
        ];

        // Vérification que le format est valide
        if (!isset($pageSizes[$format])) {
            throw new NotFoundHttpException('Format de PDF non supporté.');
        }

        // Création du PDF avec le bon format
        $pdf = new \TCPDF('P', 'mm', $pageSizes[$format]);
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

        // Positionnement et taille du logo, et autres éléments en fonction du format
        switch ($format) {
            case 'A4':
                // Logo positionné en haut du PDF
                $pdf->Image($logo, 95, 10, 20, 20, '', '', 'T', false, 300, '', false, false, 0, false, false, false);

                // Titre
                $pdf->SetFont($oswald, '', 20);
                $pdf->SetTextColor(38, 65, 66);
                $pdf->Ln(20); // Saut de ligne
                $pdf->Cell(0, 10, $paroisse->getNom(), 0, 1, 'C');
                $pdf->Ln(15);
                $pdf->SetFont($oswaldB, 'B', 45);
                $pdf->Cell(0, 10, 'Suivez la messe depuis', 0, 1, 'C');
                $pdf->Cell(0, 10, 'votre téléphone', 0, 1, 'C');

                // QR Code
                $qrCodeUrl = $routeUrl;
                $style = [
                    'border' => 0,
                    'padding' => 4,
                    'fgcolor' => [38, 65, 66],
                    'bgcolor' => [255, 255, 255],
                ];
                $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 72.5, 105, 70, 70, $style, 'N');

                // Texte explicatif
                $pdf->Ln(20);
                $pdf->SetFont($roboto, '', 30);
                $pdf->Cell(0, 10, 'Scannez le QR Code, et voilà !', 0, 1, 'C');

                // Pied de page
                $pdf->SetFont($oswald, '', 10);
                $pdf->SetY(-30);
                $pdf->Cell(0, 10, 'Proposé par ECCE MISSA', 0, 0, 'L');
                $pdf->Cell(0, 10, 'https://eccemissa.fr/', 0, 0, 'R');
                break;

            case 'A3':
                // Positionnement du logo
                $pdf->Image($logo, 135, 10, 30, 30, '', '', 'T', false, 300, '', false, false, 0, false, false, false);

                // Titre
                $pdf->SetFont($oswald, '', 30);
                $pdf->SetTextColor(38, 65, 66);
                $pdf->Ln(30); // Saut de ligne
                $pdf->Cell(0, 10, $paroisse->getNom(), 0, 1, 'C');
                $pdf->Ln(20);
                $pdf->SetFont($oswaldB, 'B', 60);
                $pdf->Cell(0, 10, 'Suivez la messe depuis', 0, 1, 'C');
                $pdf->Cell(0, 10, 'votre téléphone', 0, 1, 'C');

                // QR Code
                $qrCodeUrl = $routeUrl;
                $style = [
                    'border' => 0,
                    'padding' => 4,
                    'fgcolor' => [38, 65, 66],
                    'bgcolor' => [255, 255, 255],
                ];
                $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 120, 150, 120, 120, $style, 'N');

                // Texte explicatif
                $pdf->Ln(30);
                $pdf->SetFont($roboto, '', 40);
                $pdf->Cell(0, 10, 'Scannez le QR Code, et voilà !', 0, 1, 'C');

                // Pied de page
                $pdf->SetFont($oswald, '', 20);
                $pdf->SetY(-40);
                $pdf->Cell(0, 10, 'Proposé par ECCE MISSA', 0, 0, 'L');
                $pdf->Cell(0, 10, 'https://eccemissa.fr/', 0, 0, 'R');
                break;

            case 'A5':
                // Logo positionné plus haut pour un format A5 plus petit
                $pdf->Image($logo, 65, 10, 15, 15, '', '', 'T', false, 300, '', false, false, 0, false, false, false);

                // Titre
                $pdf->SetFont($oswald, '', 15);
                $pdf->SetTextColor(38, 65, 66);
                $pdf->Ln(20); // Saut de ligne
                $pdf->Cell(0, 10, $paroisse->getNom(), 0, 1, 'C');
                $pdf->Ln(10);
                $pdf->SetFont($oswaldB, 'B', 25);
                $pdf->Cell(0, 10, 'Suivez la messe depuis', 0, 1, 'C');
                $pdf->Cell(0, 10, 'votre téléphone', 0, 1, 'C');

                // QR Code
                $qrCodeUrl = $routeUrl;
                $style = [
                    'border' => 0,
                    'padding' => 4,
                    'fgcolor' => [38, 65, 66],
                    'bgcolor' => [255, 255, 255],
                ];
                $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 40, 80, 70, 70, $style, 'N');

                // Texte explicatif
                $pdf->Ln(10);
                $pdf->SetFont($roboto, '', 20);
                $pdf->Cell(0, 10, 'Scannez le QR Code, et voilà !', 0, 1, 'C');

                // Pied de page
                $pdf->SetFont($oswald, '', 10);
                $pdf->SetY(-20);
                $pdf->Cell(0, 10, 'Proposé par ECCE MISSA', 0, 0, 'L');
                $pdf->Cell(0, 10, 'https://eccemissa.fr/', 0, 0, 'R');
                break;

            case 'bookmark':
                // Marque-page avec une disposition plus longue
                $pdf->Image($logo, 17, 7, 15, 15, '', '', 'T', false, 300, '', false, false, 0, false, false, false);

                // Titre
                $pdf->SetFont($oswald, '', 7);
                $pdf->SetTextColor(38, 65, 66);
                $pdf->Ln(15); // Saut de ligne
                $pdf->Cell(0, 10, $paroisse->getNom(), 0, 1, 'C');
                $pdf->Ln(10);
                $pdf->SetFont($oswaldB, 'B', 12);
                $pdf->Cell(0, 10, 'Suivez la messe depuis', 0, 1, 'C');
                $pdf->Cell(0, 10, 'votre téléphone', 0, 1, 'C');

                // QR Code plus grand pour le format marque-page
                $qrCodeUrl = $routeUrl;
                $style = [
                    'border' => 0,
                    'padding' => 4,
                    'fgcolor' => [38, 65, 66],
                    'bgcolor' => [255, 255, 255],
                ];
                $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 10, 70, 50, 50, $style, 'N');

                // Texte explicatif
                $pdf->Ln(10);
                $pdf->SetFont($roboto, '', 7);
                $pdf->Cell(0, 10, 'Scannez le QR Code, et voilà !', 0, 1, 'C');

                // Pied de page
                $pdf->SetFont($oswald, '', 8);
                $pdf->SetY(-30);
                $pdf->Cell(0, 10, 'Proposé par ECCE MISSA', 0, 0, 'C');
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'https://eccemissa.fr/', 0, 0, 'C');
                break;
        }

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

    #[Route('/api/paroisse/{id}/feuilletviews', name: 'get_paroisse_feuilletviews', methods: ['GET'])]
    public function getParoisseFeuilletViews(Request $request, int $id): JsonResponse
    {
        $paroisse = $this->paroisseRepository->find($id);

        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $queryBuilder = $this->entityManager->getRepository(FeuilletView::class)->createQueryBuilder('fv')
            ->where('fv.paroisse = :paroisse')
            ->setParameter('paroisse', $paroisse);

        if ($startDate) {
            $queryBuilder->andWhere('fv.viewedAt >= :startDate') 
                ->setParameter('startDate', new \DateTimeImmutable($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('fv.viewedAt <= :endDate')
                ->setParameter('endDate', new \DateTimeImmutable($endDate));
        }

        $feuilletViews = $queryBuilder->getQuery()->getResult();

        $data = [];
        foreach ($feuilletViews as $view) {
            $data[] = [
                'feuillet_id' => $view->getFeuillet()->getId(),
                'paroisse_id' => $view->getParoisse()->getId(),
                'viewed_at' => $view->getViewedAt()->format('Y-m-d H:i')
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/paroisse/{paroisseId}/utilisateur/{userId}/changer_droits', name: 'changer_droits_utilisateur', methods: ['POST'])]
    public function changerDroitsUtilisateur(int $paroisseId, int $userId, Request $request, UserInterface $user): JsonResponse
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $paroisse = $this->paroisseRepository->find($paroisseId);
        if (!$paroisse) {
            return new JsonResponse(['error' => 'Paroisse introuvable'], 404);
        }

        $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->find($userId);
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        if (!$paroisse->getResponsable()->contains($utilisateur)) {
            return new JsonResponse(['error' => 'L\'utilisateur n\'est pas responsable de cette paroisse'], 403);
        }

        $nouveauxRoles = $request->request->get('roles');
        if (empty($nouveauxRoles) || !is_array($nouveauxRoles)) {
            return new JsonResponse(['error' => 'Rôles invalides'], 400);
        }

        $utilisateur->setRoles($nouveauxRoles);
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Droits de l\'utilisateur mis à jour'], 200);
    }

}
