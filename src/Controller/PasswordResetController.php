<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\EmailService;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

class PasswordResetController extends AbstractController
{
    private $entityManager;
    private $emailService;
    private $tokenGenerator;

    public function __construct(EntityManagerInterface $entityManager, EmailService $emailService, TokenGenerator $tokenGenerator)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->tokenGenerator = $tokenGenerator;
    }

    #[Route('/api/password-reset/request', methods: ['POST'])]
    public function requestReset(Request $request, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $email = $request->request->get('email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'E-mail invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        if (!$utilisateur) {
            return $this->json(['error' => 'Aucun utilisateur trouvé avec cet e-mail'], JsonResponse::HTTP_NOT_FOUND);
        }

        $token = $this->tokenGenerator->generateToken();
        $utilisateur->setResetToken($token);
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $this->emailService->sendPasswordResetEmail($email, $token);

        return $this->json(['message' => 'E-mail de réinitialisation de mot de passe envoyé.']);
    }

    #[Route('/api/password-reset/reset', methods: ['POST'])]
    public function resetPassword(Request $request, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $token = $request->request->get('token');
        $newPassword = $request->request->get('password');
        $newPasswordConfirm = $request->request->get('confirm_password');

        if ($newPassword !== $newPasswordConfirm) {
            return $this->json(['error' => 'Les mots de passe ne correspondent pas'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (empty($token) || empty($newPassword) || strlen($newPassword) < 6) {
            return $this->json(['error' => 'Token ou mot de passe invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Find user by token (this part depends on how you store the token)
        $utilisateur = $utilisateurRepository->findOneBy(['resetToken' => $token]);

        if (!$utilisateur) {
            return $this->json(['error' => 'Token invalide ou expiré'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Hash the new password and update the user
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $utilisateur->setPassword($hashedPassword);
        $utilisateur->setResetToken(null);

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}