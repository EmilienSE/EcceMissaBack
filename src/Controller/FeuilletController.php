<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FeuilletController extends AbstractController
{
    #[Route('/api/feuillet', name: 'app_feuillet')]
    //#[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour afficher un feuillet')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/FeuilletController.php',
        ]);
    }
}
