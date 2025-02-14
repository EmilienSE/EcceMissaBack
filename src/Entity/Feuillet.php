<?php

namespace App\Entity;

use App\Repository\FeuilletRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeuilletRepository::class)]
class Feuillet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Description = null;

    #[ORM\ManyToOne(inversedBy: 'feuillets')]
    private ?Utilisateur $Utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'feuillets')]
    private ?Eglise $Eglise = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $celebrationDate = null;

    #[ORM\ManyToOne(inversedBy: 'feuillets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Paroisse $paroisse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileUrl = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $viewCount = 0;

    /**
     * @var Collection<int, FeuilletView>
     */
    #[ORM\OneToMany(targetEntity: FeuilletView::class, mappedBy: 'feuillet', orphanRemoval: true)]
    private Collection $feuilletViews;

    public function __construct()
    {
        $this->feuilletViews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->Utilisateur;
    }

    public function setUtilisateur(?Utilisateur $Utilisateur): static
    {
        $this->Utilisateur = $Utilisateur;

        return $this;
    }

    public function getEglise(): ?Eglise
    {
        return $this->Eglise;
    }

    public function setEglise(?Eglise $Eglise): static
    {
        $this->Eglise = $Eglise;

        return $this;
    }

    public function getCelebrationDate(): ?\DateTimeInterface
    {
        return $this->celebrationDate;
    }

    public function setCelebrationDate(\DateTimeInterface $celebrationDate): static
    {
        $this->celebrationDate = $celebrationDate;

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

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount + $this->feuilletViews->count();
    }

    public function incrementViewCount(): self
    {
        $this->viewCount++;
        return $this;
    }

    /**
     * @return Collection<int, FeuilletView>
     */
    public function getFeuilletViews(): Collection
    {
        return $this->feuilletViews;
    }

    public function addFeuilletView(FeuilletView $feuilletView): static
    {
        if (!$this->feuilletViews->contains($feuilletView)) {
            $this->feuilletViews->add($feuilletView);
            $feuilletView->setFeuillet($this);
        }

        return $this;
    }

    public function removeFeuilletView(FeuilletView $feuilletView): static
    {
        if ($this->feuilletViews->removeElement($feuilletView)) {
            // set the owning side to null (unless already changed)
            if ($feuilletView->getFeuillet() === $this) {
                $feuilletView->setFeuillet(null);
            }
        }

        return $this;
    }
}
