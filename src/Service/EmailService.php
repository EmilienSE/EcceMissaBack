<?php

// src/Service/EmailService.php
namespace App\Service;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class EmailService
{
    private $mailer;
    private $twig;
    private $from;
    private $to;

    public function __construct(MailerInterface $mailer, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->from = "test@demomailtrap.com";
        $this->to = "mcdeux49@gmail.com";
    }

    public function sendCreationCompteEmail(string $nom, string $prenom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = 'Bienvenue sur Ecce Missa !';

        $htmlContent = $this->twig->render('emails/creation_compte.html.twig', [
            'subject' => $subject,
            'user_name' => $userName,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($this->to)
            ->subject($subject)
            ->html($htmlContent);

        $imagePath = 'logo.png';
        $email->embedFromPath($imagePath, 'logo.png');

        $this->mailer->send($email);
    }
    
    public function sendParoisseRejointeEmail(string $nom, string $prenom, string $paroisse, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $paroisseName = $paroisse;
        $userEmail = $email;
        $subject = 'Paroisse rejointe';

        $htmlContent = $this->twig->render('emails/paroisse_rejointe.html.twig', [
            'subject' => $subject,
            'user_name' => $userName,
            'paroisse_name' => $paroisseName,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($this->to)
            ->subject($subject)
            ->html($htmlContent);

        $imagePath = 'logo.png';
        $email->embedFromPath($imagePath, 'logo.png');

        $this->mailer->send($email);
    }
}
