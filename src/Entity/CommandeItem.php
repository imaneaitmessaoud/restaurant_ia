<?php

namespace App\Entity;

use App\Repository\CommandeItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeItemRepository::class)]

#[ORM\Table(name: 'commande_items')]
class CommandeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $prixUnitaire = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $personalisationJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relation avec Commande
    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'commandeItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    // Relation avec MenuItem ✅ RELATION CLÉE
    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'commandeItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MenuItem $menuItem = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->quantite = 1;
        $this->personalisationJson = [];
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = max(1, $quantite);
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPersonalisationJson(): ?array
    {
        return $this->personalisationJson;
    }

    public function setPersonalisationJson(?array $personalisationJson): static
    {
        $this->personalisationJson = $personalisationJson ?? [];
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

    // Relations
    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMenuItem(): ?MenuItem
    {
        return $this->menuItem;
    }

    public function setMenuItem(?MenuItem $menuItem): static
    {
        $this->menuItem = $menuItem;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Méthodes utilitaires
    public function getPrixUnitaireFloat(): float
    {
        return (float) $this->prixUnitaire;
    }

    public function setPrixUnitaireFloat(float $prix): static
    {
        $this->prixUnitaire = (string) $prix;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSousTotal(): float
    {
        return $this->getPrixUnitaireFloat() * $this->quantite;
    }

    public function getFormattedSousTotal(): string
    {
        return number_format($this->getSousTotal(), 2) . ' DH';
    }

    public function getDisplayName(): string
    {
        $name = $this->menuItem?->getNom() ?: 'Article';
        if ($this->quantite > 1) {
            $name = $this->quantite . 'x ' . $name;
        }
        return $name;
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'commande_id' => $this->commande?->getId(),
            'menu_item_id' => $this->menuItem?->getId(),
            'menu_item_name' => $this->menuItem?->getNom(),
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->getPrixUnitaireFloat(),
            'sous_total' => $this->getSousTotal(),
            'sous_total_formatted' => $this->getFormattedSousTotal(),
            'personalisation' => $this->personalisationJson,
            'commentaire' => $this->commentaire,
            'display_name' => $this->getDisplayName(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->getFormattedSousTotal());
    }
}