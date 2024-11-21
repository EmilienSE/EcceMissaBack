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
        
        $this->send($subject, $htmlContent, $userEmail);
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
        
        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendNouvelUtilisateurParoisseEmail(string $paroisse, string $prenom, string $nom, string $email, string $nouveauNom, string $nouveauPrenom): void
    {
        $userName = $prenom.' '.$nom;
        $nouveauUserName = $nouveauPrenom.' '.$nouveauNom;
        $paroisseName = $paroisse;
        $userEmail = $email;
        $subject = 'Paroisse rejointe';

        $htmlContent = $this->twig->render('emails/nouvel_utilisateur_paroisse.html.twig', [
            'subject' => $subject,
            'user_name' => $userName,
            'nouveau_user_name' => $nouveauUserName,
            'paroisse_name' => $paroisseName,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendUtilisateurSupprimeEmail(string $nom, string $prenom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = 'Au revoir '.$prenom.'!';

        $htmlContent = $this->twig->render('emails/utilisateur_supprime.html.twig', [
            'subject' => $subject,
            'user_name' => $userName,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendParoisseAjouteeEmail(string $paroisse, string $prenom, string $nom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = 'La paroisse '.$paroisse.' a bien été ajoutée.';

        $htmlContent = $this->twig->render('emails/paroisse_ajoutee.html.twig', [
            'paroisse' => $paroisse,
            'user_name' => $userName,
            'subject' => $subject,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendEchecPaiementEmail(string $paroisse, string $prenom, string $nom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = 'Le paiement pour la paroisse '.$paroisse.' a échoué.';

        $htmlContent = $this->twig->render('emails/echec_paiement.html.twig', [
            'paroisse' => $paroisse,
            'user_name' => $userName,
            'subject' => $subject,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendSuccesPaiementEmail(string $paroisse, string $prenom, string $nom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = 'Le paiement pour la paroisse '.$paroisse.' a bien été effectué.';

        $htmlContent = $this->twig->render('emails/succes_paiement.html.twig', [
            'paroisse' => $paroisse,
            'user_name' => $userName,
            'subject' => $subject,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }
    
    public function sendAbonnementAnnuleEmail(string $paroisse, string $prenom, string $nom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = "L'abonnement de la paroisse ".$paroisse." a été annulé.";

        $htmlContent = $this->twig->render('emails/abonnement_annule.html.twig', [
            'paroisse' => $paroisse,
            'user_name' => $userName,
            'subject' => $subject,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }

    public function sendParoisseSupprimeeEmail(string $paroisse, string $prenom, string $nom, string $email): void
    {
        $userName = $prenom.' '.$nom;
        $userEmail = $email;
        $subject = "La paroisse ".$paroisse." a été supprimée d'Ecce Missa.";

        $htmlContent = $this->twig->render('emails/paroisse_supprimee.html.twig', [
            'paroisse' => $paroisse,
            'user_name' => $userName,
            'subject' => $subject,
        ]);

        $this->send($subject, $htmlContent, $userEmail);
    }

    private function send(string $subject, string $htmlContent, string $to): void
    {
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
