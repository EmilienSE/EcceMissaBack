<?php

namespace App\Entity;

use App\Repository\EgliseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EgliseRepository::class)]
class Eglise
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
     * @var Collection<int, Feuillet>
     */
    #[ORM\OneToMany(targetEntity: Feuillet::class, mappedBy: 'Eglise')]
    private Collection $feuillets;

    #[ORM\ManyToOne(inversedBy: 'eglises')]
    private ?Paroisse $Paroisse = null;

    /**
     * @var Collection<int, Utilisateur>
     */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'eglises')]
    private Collection $Responsable;

    public function __construct()
    {
        $this->feuillets = new ArrayCollection();
        $this->Responsable = new ArrayCollection();
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
            $feuillet->setEglise($this);
        }

        return $this;
    }

    public function removeFeuillet(Feuillet $feuillet): static
    {
        if ($this->feuillets->removeElement($feuillet)) {
            // set the owning side to null (unless already changed)
            if ($feuillet->getEglise() === $this) {
                $feuillet->setEglise(null);
            }
        }

        return $this;
    }

    public function getParoisse(): ?Paroisse
    {
        return $this->Paroisse;
    }

    public function setParoisse(?Paroisse $Paroisse): static
    {
        $this->Paroisse = $Paroisse;

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
        }

        return $this;
    }

    public function removeResponsable(Utilisateur $responsable): static
    {
        $this->Responsable->removeElement($responsable);

        return $this;
    }
}
