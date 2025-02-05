<?php 

namespace App\MessageHandler;

use App\Message\IncrementFeuilletViewMessage;
use App\Repository\FeuilletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class IncrementFeuilletViewHandler
{
    private FeuilletRepository $feuilletRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(FeuilletRepository $feuilletRepository, EntityManagerInterface $entityManager)
    {
        $this->feuilletRepository = $feuilletRepository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(IncrementFeuilletViewMessage $message)
    {
        $feuillet = $this->feuilletRepository->find($message->getFeuilletId());

        if (!$feuillet) {
            return;
        }

        // Incrémentation du compteur de vues
        $feuillet->incrementViewCount();
        $this->entityManager->persist($feuillet);
        $this->entityManager->flush();
    }
}
