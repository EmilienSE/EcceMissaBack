<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Utilisateur;

class UtilisateurController extends AbstractController
{
    #[Route('/api/inscription', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Récupérer les données JSON
        $email = $request->request->get('email') ?? null;
        $prenom = $request->request->get('prenom') ?? null;
        $nom = $request->request->get('nom') ?? null;
        $password = $request->request->get('password') ?? null;
        
        // Vérification manuelle des données
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
        $utilisateur->setEnabled(true);
        $utilisateur->setPrenom($prenom);
        
        // Hacher le mot de passe
        $hashedPassword = $passwordHasher->hashPassword($utilisateur, $password);
        $utilisateur->setPassword($hashedPassword);

        // Enregistrer l'utilisateur dans la base de données
        $entityManager->persist($utilisateur);
        $entityManager->flush();

        return $this->json(['message' => 'Utilisateur créé avec succès.'], JsonResponse::HTTP_CREATED);
    }
}
