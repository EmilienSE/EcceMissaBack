<?php

namespace App\Controller;

use App\Service\EmailService;
use Error;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    #[Route('/contact', name: 'contact', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $phone = $request->query->get('phone');
            $message = $request->query->get('message');
            $prenom = $request->query->get('prenom');
            $nom = $request->query->get('nom');
            $email = $request->query->get('email');
    
            $this->emailService->sendContactEmail($phone, $message, $prenom, $nom, $email);

            return new JsonResponse('Message envoyé');
        } catch (Error $err){
            return new JsonResponse($err, 500);
        }
    }
}
