<?php

namespace App\Entity;

use App\Repository\FeuilletViewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeuilletViewRepository::class)]
class FeuilletView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'feuilletViews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Feuillet $feuillet = null;

    #[ORM\ManyToOne(inversedBy: 'feuilletViews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Paroisse $paroisse = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $viewedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeuillet(): ?Feuillet
    {
        return $this->feuillet;
    }

    public function setFeuillet(?Feuillet $feuillet): static
    {
        $this->feuillet = $feuillet;

        return $this;
    }

    public function getParoisse(): ?Paroisse
    {
        return $this->paroisse;
    }

    public function setParoisse(?Paroisse $paroisse): static
    {
        $this->paroisse = $paroisse;

        return $this;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(\DateTimeImmutable $viewedAt): static
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }
}
