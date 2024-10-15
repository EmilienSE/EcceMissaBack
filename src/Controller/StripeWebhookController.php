<?php 

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ParoisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use App\Entity\Paroisse;

class StripeWebhookController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        // Votre clé secrète de webhook depuis le tableau de bord Stripe
        $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        // Récupérez le corps de la requête
        $payload = $request->getContent();
        $sig_header = $request->headers->get('Stripe-Signature');

        try {
            // Vérifiez que l'événement vient bien de Stripe
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            // Payload invalide
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Signature invalide
            return new Response('Invalid signature', 400);
        }

        // Gérer différents types d'événements Stripe
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $invoice = $event->data->object; // Contient les informations de la facture
                
                // Vous pouvez obtenir le champ `metadata` de la facture pour identifier la paroisse
                $paroisseId = $invoice->subscription_details->metadata->paroisse_id ?? null;

                if ($paroisseId) {
                    $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

                    if ($paroisse) {
                        // Mettre à jour l'état du paiement de la paroisse
                        $paroisse->setPaiementAJour(true);
                        $this->entityManager->persist($paroisse);
                        $this->entityManager->flush();
                    }
                }
                break;
            case 'customer.subscription.created':
                // Récupérer l'abonnement Stripe et son ID
                $subscription = $event->data->object; // Contient les informations de l'abonnement
                $stripeSubscriptionId = $subscription->id;
    
                // Obtenir le champ `metadata` pour identifier la paroisse
                $paroisseId = $subscription->metadata->paroisse_id ?? null;
    
                if ($paroisseId) {
                    $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);
    
                    if ($paroisse) {
                        // Associer l'ID d'abonnement Stripe à la paroisse
                        $paroisse->setStripeSubscriptionId($stripeSubscriptionId);
                        $this->entityManager->persist($paroisse);
                        $this->entityManager->flush();
                    }
                }
                break;
            case 'customer.subscription.deleted':
                $invoice = $event->data->object; // Contient les informations de la facture

                // Vous pouvez obtenir le champ `metadata` de la facture pour identifier la paroisse
                $paroisseId = $invoice->metadata->paroisse_id ?? null;

                if ($paroisseId) {
                    $paroisse = $this->entityManager->getRepository(Paroisse::class)->find($paroisseId);

                    if ($paroisse) {
                        // Mettre à jour l'état du paiement de la paroisse
                        $paroisse->setPaiementAJour(false);
                        $this->entityManager->persist($paroisse);
                        $this->entityManager->flush();
                    }
                }
                break;
            default:
                // Gérer d'autres événements
                break;
        }

        return new Response('Webhook received', 200);
    }
}
