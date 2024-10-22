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

class UtilisateurController extends AbstractController
{
    private $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/inscription', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Récupérer les données JSON
        $email = $request->request->get('email') ?? null;
        $prenom = $request->request->get('prenom') ?? null;
        $nom = $request->request->get('nom') ?? null;
        $password = $request->request->get('password') ?? null;

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

        // Hacher le mot de passe
        $hashedPassword = $passwordHasher->hashPassword($utilisateur, $password);
        $utilisateur->setPassword($hashedPassword);

        // Enregistrer l'utilisateur dans la base de données
        $entityManager->persist($utilisateur);
        $entityManager->flush();

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

    // Supprimer un utilisateur
    #[Route('/api/utilisateur/{id}', methods: ['DELETE'])]
    public function deleteUtilisateur(int $id, EntityManagerInterface $entityManager, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $utilisateur = $utilisateurRepository->find($id);

        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        $entityManager->remove($utilisateur);
        $entityManager->flush();

        return $this->json(['message' => 'Utilisateur supprimé avec succès.']);
    }
}
