<?php

namespace App\Entity;

use App\Repository\ParoisseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParoisseRepository::class)]
class Paroisse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Nom = null;

    #[ORM\Column(length: 255)]
    private ?string $GPS = null;

    /**
     * @var Collection<int, Eglise>
     */
    #[ORM\OneToMany(targetEntity: Eglise::class, mappedBy: 'Paroisse')]
    private Collection $eglises;

    #[ORM\ManyToOne(inversedBy: 'paroisses')]
    private ?Diocese $Diocese = null;

    /**
     * @var Collection<int, Utilisateur>
     */
    #[ORM\OneToMany(targetEntity: Utilisateur::class, mappedBy: 'paroisse')]
    private Collection $Responsable;

    /**
     * @var Collection<int, Feuillet>
     */
    #[ORM\OneToMany(targetEntity: Feuillet::class, mappedBy: 'paroisse', orphanRemoval: true)]
    private Collection $feuillets;

    #[ORM\Column(length: 255, nullable:true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $paiementAJour = false;

    #[ORM\Column(length: 255, nullable:true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(length: 10, nullable:true)]
    private ?string $codeUnique = null;

    public function __construct()
    {
        $this->eglises = new ArrayCollection();
        $this->Responsable = new ArrayCollection();
        $this->feuillets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->Nom;
    }

    public function setNom(string $Nom): static
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function getGPS(): ?string
    {
        return $this->GPS;
    }

    public function setGPS(string $GPS): static
    {
        $this->GPS = $GPS;

        return $this;
    }

    /**
     * @return Collection<int, Eglise>
     */
    public function getEglises(): Collection
    {
        return $this->eglises;
    }

    public function addEglise(Eglise $eglise): static
    {
        if (!$this->eglises->contains($eglise)) {
            $this->eglises->add($eglise);
            $eglise->setParoisse($this);
        }

        return $this;
    }

    public function removeEglise(Eglise $eglise): static
    {
        if ($this->eglises->removeElement($eglise)) {
            // set the owning side to null (unless already changed)
            if ($eglise->getParoisse() === $this) {
                $eglise->setParoisse(null);
            }
        }

        return $this;
    }

    public function getDiocese(): ?Diocese
    {
        return $this->Diocese;
    }

    public function setDiocese(?Diocese $Diocese): static
    {
        $this->Diocese = $Diocese;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getResponsable(): Collection
    {
        return $this->Responsable;
    }

    public function addResponsable(Utilisateur $responsable): static
    {
        if (!$this->Responsable->contains($responsable)) {
            $this->Responsable->add($responsable);
            $responsable->setParoisse($this);
        }

        return $this;
    }

    public function removeResponsable(Utilisateur $responsable): static
    {
        if ($this->Responsable->removeElement($responsable)) {
            // set the owning side to null (unless already changed)
            if ($responsable->getParoisse() === $this) {
                $responsable->setParoisse(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Feuillet>
     */
    public function getFeuillets(): Collection
    {
        return $this->feuillets;
    }

    public function addFeuillet(Feuillet $feuillet): static
    {
        if (!$this->feuillets->contains($feuillet)) {
            $this->feuillets->add($feuillet);
            $feuillet->setParoisse($this);
        }

        return $this;
    }

    public function removeFeuillet(Feuillet $feuillet): static
    {
        if ($this->feuillets->removeElement($feuillet)) {
            // set the owning side to null (unless already changed)
            if ($feuillet->getParoisse() === $this) {
                $feuillet->setParoisse(null);
            }
        }

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function isPaiementAJour(): bool
    {
        return $this->paiementAJour;
    }

    public function setPaiementAJour(bool $paiementAJour): static
    {
        $this->paiementAJour = $paiementAJour;

        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): self
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;

        return $this;
    }

    public function getCodeUnique(): ?string
    {
        return $this->codeUnique;
    }

    public function setCodeUnique(?string $codeUnique): self
    {
        $this->codeUnique = $codeUnique;

        return $this;
    }
}
