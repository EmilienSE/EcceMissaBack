<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Paroisse;
use App\Service\EmailService;

class UtilisateurController extends AbstractController
{
    private $entityManager;
    private $emailService;

    public function __construct(EntityManagerInterface $entityManager, EmailService $emailService)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
    }

    #[Route('/api/inscription', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Récupérer les données JSON
        $email = $request->request->get('email') ?? null;
        $prenom = $request->request->get('prenom') ?? null;
        $nom = $request->request->get('nom') ?? null;
        $password = $request->request->get('password') ?? null;
        $termsAccepted = $request->request->get('termsAccepted') ?? null;

        if (empty($termsAccepted) || $termsAccepted !== 'true') {
            return $this->json(['error' => 'Les termes doivent être acceptés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Vérification des données
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'E-mail invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (empty($nom)) {
            return $this->json(['error' => 'Aucun nom n\'a été envoyé.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (empty($prenom)) {
            return $this->json(['error' => 'Aucun prénom n\'a été envoyé.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (empty($password) || strlen($password) < 6) {
            return $this->json(['error' => 'Le mot de passe doit être d\'au moins 6 caractères.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Créer un nouvel utilisateur
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNom($nom);
        $utilisateur->setPrenom($prenom);
        $utilisateur->setEnabled(true);
        $utilisateur->acceptTerms(true);

        // Hacher le mot de passe
        $hashedPassword = $passwordHasher->hashPassword($utilisateur, $password);
        $utilisateur->setPassword($hashedPassword);

        // Enregistrer l'utilisateur dans la base de données
        $entityManager->persist($utilisateur);
        $entityManager->flush();

        $this->emailService->sendCreationCompteEmail($nom, $prenom, $email);

        return $this->json(['message' => 'Utilisateur créé avec succès.'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/utilisateur/{id}', methods: ['GET'])]
    public function getUtilisateur(int $id, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $utilisateur = $utilisateurRepository->find($id);

        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($utilisateur);
    }
    
    #[Route('/api/utilisateur', methods: ['GET'])]
    public function getUtilisateurInfos(UserInterface $user, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $utilisateur = $utilisateurRepository->find($user);

        if(!$utilisateur) {
            return $this->json(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $utilisateur->getId(),
            'nom' => $utilisateur->getNom(),
            'prenom' => $utilisateur->getPrenom(),
            'email' => $utilisateur->getEmail(),
            'paroisse' => $utilisateur->getParoisse() ? $utilisateur->getParoisse()->getNom() : ''
        ]);
    }

    // Lire tous les utilisateurs
    #[Route('/api/utilisateurs', methods: ['GET'])]
    public function getUtilisateurs(UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $utilisateurs = $utilisateurRepository->findAll();

        return $this->json($utilisateurs);
    }

    #[Route('/api/utilisateur/modifier-mot-de-passe', methods: ['POST'])]
    public function changePassword(Request $request, UtilisateurRepository $utilisateurRepository, UserPasswordHasherInterface $passwordHasher, UserInterface $currentUser): JsonResponse {
        // Récupérer l'utilisateur actuel
        $utilisateur = $utilisateurRepository->find($currentUser);

        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Récupérer les données du mot de passe
        $ancienPassword = $request->request->get('ancien_password');
        $nouveauPassword = $request->request->get('nouveau_password');

        // Vérifier que les deux mots de passe sont remplis
        if (empty($ancienPassword) || empty($nouveauPassword)) {
            return $this->json(['error' => 'Veuillez fournir tous les champs requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Vérifier que l'ancien mot de passe est correct
        if (!$passwordHasher->isPasswordValid($utilisateur, $ancienPassword)) {
            return $this->json(['error' => 'L\'ancien mot de passe est incorrect.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Vérifier que le nouveau mot de passe respecte les critères de sécurité
        if (strlen($nouveauPassword) < 6) {
            return $this->json(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Hacher le nouveau mot de passe et le mettre à jour
        $hashedPassword = $passwordHasher->hashPassword($utilisateur, $nouveauPassword);
        $utilisateur->setPassword($hashedPassword);

        // Enregistrer les modifications
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    #[Route('/api/utilisateur/delete', name: 'delete_utilisateur', methods: ['POST'])]
    public function deleteUtilisateur(Request $request, UserInterface $user): JsonResponse
    {
        // Rechercher la paroisse de l'utilisateur
        $paroisse = $this->entityManager->getRepository(Utilisateur::class)->find($user)->getParoisse();

        if ($paroisse && $paroisse->getResponsable()->contains($user)) {        
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

        }

        // Sauvegarder les changements
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->emailService->sendUtilisateurSupprimeEmail($user->getNom(), $user->getPrenom(), $user->getEmail());

        return new JsonResponse(['message' => 'Vous avez quitté la paroisse'], 200);
    }

    // Mettre à jour un utilisateur
    #[Route('/api/utilisateur/{id}', methods: ['POST'])]
    public function updateUtilisateur(int $id, Request $request, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $utilisateur = $utilisateurRepository->find($id);

        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        $email = $request->request->get('email') ?? null;
        $prenom = $request->request->get('prenom') ?? null;
        $nom = $request->request->get('nom') ?? null;

        // Mise à jour des champs
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $utilisateur->setEmail($email);
        }

        if (!empty($nom)) {
            $utilisateur->setNom($nom);
        }

        if (!empty($prenom)) {
            $utilisateur->setPrenom($prenom);
        }

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $this->json(['message' => 'Utilisateur mis à jour avec succès.']);
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
