<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(length: 255)]
    private ?string $Nom = null;

    #[ORM\Column(length: 255)]
    private ?string $Prenom = null;

    #[ORM\Column]
    private ?bool $Enabled = null;

    #[ORM\Column]
    private ?bool $termsAccepted = null;

    /**
     * @var Collection<int, Feuillet>
     */
    #[ORM\OneToMany(targetEntity: Feuillet::class, mappedBy: 'Utilisateur')]
    private Collection $feuillets;

    /**
     * @var Collection<int, Eglise>
     */
    #[ORM\ManyToMany(targetEntity: Eglise::class, mappedBy: 'Responsable')]
    private Collection $eglises;

    #[ORM\ManyToOne(inversedBy: 'Responsable')]
    private ?Paroisse $paroisse = null;

    /**
     * @var Collection<int, Diocese>
     */
    #[ORM\OneToMany(targetEntity: Diocese::class, mappedBy: 'Responsable')]
    private Collection $dioceses;

    public function __construct()
    {
        $this->feuillets = new ArrayCollection();
        $this->eglises = new ArrayCollection();
        $this->dioceses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
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

    public function getPrenom(): ?string
    {
        return $this->Prenom;
    }

    public function setPrenom(string $Prenom): static
    {
        $this->Prenom = $Prenom;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isEnabled(): ?bool
    {
        return $this->Enabled;
    }

    public function setEnabled(bool $Enabled): static
    {
        $this->Enabled = $Enabled;

        return $this;
    }

    public function hasAcceptedTerms(): ?bool
    {
        return $this->termsAccepted;
    }

    public function acceptTerms(bool $termsAccepted): static
    {
        $this->termsAccepted = $termsAccepted;

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
            $feuillet->setUtilisateur($this);
        }

        return $this;
    }

    public function removeFeuillet(Feuillet $feuillet): static
    {
        if ($this->feuillets->removeElement($feuillet)) {
            // set the owning side to null (unless already changed)
            if ($feuillet->getUtilisateur() === $this) {
                $feuillet->setUtilisateur(null);
            }
        }

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
            $eglise->addResponsable($this);
        }

        return $this;
    }

    public function removeEglise(Eglise $eglise): static
    {
        if ($this->eglises->removeElement($eglise)) {
            $eglise->removeResponsable($this);
        }

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

    /**
     * @return Collection<int, Diocese>
     */
    public function getDioceses(): Collection
    {
        return $this->dioceses;
    }

    public function addDiocese(Diocese $diocese): static
    {
        if (!$this->dioceses->contains($diocese)) {
            $this->dioceses->add($diocese);
            $diocese->setResponsable($this);
        }

        return $this;
    }

    public function removeDiocese(Diocese $diocese): static
    {
        if ($this->dioceses->removeElement($diocese)) {
            // set the owning side to null (unless already changed)
            if ($diocese->getResponsable() === $this) {
                $diocese->setResponsable(null);
            }
        }

        return $this;
    }

    /**
     * Méthode getUsername qui permet de retourner le champ qui est utilisé pour l'authentification.
     *
     * @return string
     */
    public function getUsername(): string {
        return $this->getUserIdentifier();
    }
}