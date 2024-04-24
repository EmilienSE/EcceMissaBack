<?php

namespace App\Entity;

use App\Repository\DioceseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DioceseRepository::class)]
class Diocese
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Nom = null;

    /**
     * @var Collection<int, Paroisse>
     */
    #[ORM\OneToMany(targetEntity: Paroisse::class, mappedBy: 'Diocese')]
    private Collection $paroisses;

    #[ORM\ManyToOne(inversedBy: 'dioceses')]
    private ?Utilisateur $Responsable = null;

    public function __construct()
    {
        $this->paroisses = new ArrayCollection();
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

    /**
     * @return Collection<int, Paroisse>
     */
    public function getParoisses(): Collection
    {
        return $this->paroisses;
    }

    public function addParoiss(Paroisse $paroiss): static
    {
        if (!$this->paroisses->contains($paroiss)) {
            $this->paroisses->add($paroiss);
            $paroiss->setDiocese($this);
        }

        return $this;
    }

    public function removeParoiss(Paroisse $paroiss): static
    {
        if ($this->paroisses->removeElement($paroiss)) {
            // set the owning side to null (unless already changed)
            if ($paroiss->getDiocese() === $this) {
                $paroiss->setDiocese(null);
            }
        }

        return $this;
    }

    public function getResponsable(): ?Utilisateur
    {
        return $this->Responsable;
    }

    public function setResponsable(?Utilisateur $Responsable): static
    {
        $this->Responsable = $Responsable;

        return $this;
    }
}
