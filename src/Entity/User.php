<?php

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Enum\StatutUserEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', enumType: RoleEnum::class)]
    private RoleEnum $role = RoleEnum::CLIENT;

    #[ORM\Column(type: 'string', enumType: StatutUserEnum::class)]
    private StatutUserEnum $statut = StatutUserEnum::ACTIF;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations bidirectionnelles
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Commande::class)]
    private Collection $commandes;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Conversation::class)]
    private Collection $conversations;
    
    //CustomerPreference
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CustomerPreference::class, cascade: ['persist', 'remove'])]
    private ?CustomerPreference $preference = null;

    public function getPreference(): ?CustomerPreference
    {
        return $this->preference;
    }

    public function setPreference(CustomerPreference $preference): static
    {
        if ($preference->getUser() !== $this) {
            $preference->setUser($this);
        }

        $this->preference = $preference;
        return $this;
    }

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters et Setters de base
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower($email);
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRole(): RoleEnum
    {
        return $this->role;
    }

    public function setRole(RoleEnum $role): static
    {
        $this->role = $role;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatut(): StatutUserEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutUserEnum $statut): static
    {
        $this->statut = $statut;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Méthodes de relations bidirectionnelles - Commandes
    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUser($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            // set the owning side to null (unless already changed)
            if ($commande->getUser() === $this) {
                $commande->setUser(null);
            }
        }

        return $this;
    }

    // Méthodes de relations bidirectionnelles - Conversations
    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setUser($this);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation)) {
            // set the owning side to null (unless already changed)
            if ($conversation->getUser() === $this) {
                $conversation->setUser(null);
            }
        }

        return $this;
    }

    // Méthodes UserInterface (pour l'authentification Symfony)
    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role->value)];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données sensibles temporaires sur l'utilisateur, effacez-les ici
    }

    // Méthodes utilitaires
    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::ADMIN;
    }

    public function isClient(): bool
    {
        return $this->role === RoleEnum::CLIENT;
    }

    public function isActive(): bool
    {
        return $this->statut === StatutUserEnum::ACTIF;
    }

    public function canLogin(): bool
    {
        return $this->statut === StatutUserEnum::ACTIF;
    }

    public function getDisplayName(): string
    {
        return $this->nom ?: $this->email;
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role->value,
            'role_label' => $this->role->getLabel(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->getLabel(),
            'is_admin' => $this->isAdmin(),
            'is_active' => $this->isActive(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    // toString pour le débogage
    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->email);
    }
}