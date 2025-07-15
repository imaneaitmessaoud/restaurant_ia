<?php

namespace App\Entity;

use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commandes')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(type: 'string', enumType: StatutCommandeEnum::class)]
    private StatutCommandeEnum $statut = StatutCommandeEnum::CONFIRMEE;

    #[ORM\Column(type: 'string', enumType: TypeServiceEnum::class)]
    private TypeServiceEnum $typeService = TypeServiceEnum::SUR_PLACE;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adresseLivraison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    // Relation avec User
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // TODO: Relation avec CommandeItem - À compléter par votre collègue
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeItem::class, cascade: ['persist', 'remove'])]
    private Collection $commandeItems;

    public function __construct()
    {
        $this->commandeItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->statut = StatutCommandeEnum::CONFIRMEE;
        $this->typeService = TypeServiceEnum::SUR_PLACE;
    }

    // Getters et Setters de base...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatut(): StatutCommandeEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutCommandeEnum $statut): static
    {
        $this->statut = $statut;
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($statut === StatutCommandeEnum::CONFIRMEE && !$this->confirmedAt) {
            $this->confirmedAt = new \DateTimeImmutable();
        }
        if ($statut === StatutCommandeEnum::LIVREE && !$this->deliveredAt) {
            $this->deliveredAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getTypeService(): TypeServiceEnum
    {
        return $this->typeService;
    }

    public function setTypeService(TypeServiceEnum $typeService): static
    {
        $this->typeService = $typeService;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?string $adresseLivraison): static
    {
        $this->adresseLivraison = $adresseLivraison;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
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

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    // Relation avec User
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Méthodes utilitaires de base
    public function getTotalFloat(): float
    {
        return (float) $this->total;
    }

    public function setTotalFloat(float $total): static
    {
        $this->total = (string) $total;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFormattedTotal(): string
    {
        return number_format($this->getTotalFloat(), 2) . ' DH';
    }

    public function getReference(): string
    {
        return sprintf('CMD-%06d', $this->id);
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->getReference(),
            'user_id' => $this->user?->getId(),
            'user_name' => $this->user?->getNom(),
            'total' => $this->getTotalFloat(),
            'total_formatted' => $this->getFormattedTotal(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->getLabel(),
            'type_service' => $this->typeService->value,
            'type_service_label' => $this->typeService->getLabel(),
            'adresse_livraison' => $this->adresseLivraison,
            'commentaire' => $this->commentaire,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'confirmed_at' => $this->confirmedAt?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->deliveredAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getReference(), $this->getFormattedTotal());
    }

    public function getCommandeItems(): Collection
    {
        return $this->commandeItems;
    }

    public function addCommandeItem(CommandeItem $item): self
    {
        if (!$this->commandeItems->contains($item)) {
            $this->commandeItems[] = $item;
            $item->setCommande($this); // relation inverse
        }

        return $this;
    }

    public function removeCommandeItem(CommandeItem $item): self
    {
        if ($this->commandeItems->removeElement($item)) {
            if ($item->getCommande() === $this) {
                $item->setCommande(null);
            }
        }

        return $this;
    }
    public function getItemsCount(): int
    {
        return count($this->commandeItems);
    }

    public function getTotalQuantity(): int
    {
        return array_sum(array_map(fn($item) => $item->getQuantite(), $this->commandeItems->toArray()));
    }

    public function calculateTotal(): float
    {
        return array_reduce($this->commandeItems->toArray(), fn($sum, $item) =>
            $sum + $item->getSousTotal(), 0.0);
    }

    public function updateTotal(): void
    {
        $this->setTotal($this->calculateTotal());
    }

    public function isDelivery(): bool
    {
        return $this->typeService === TypeServiceEnum::LIVRAISON;
    }

}