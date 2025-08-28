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
    private ?int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $prixUnitaire = '0.00';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $personalisationJson = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // --- Relation Commande (obligatoire)
    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'commandeItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    // --- Relation MenuItem (FACULTATIVE pour Excel)
    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'commandeItems')]
    #[ORM\JoinColumn(nullable: true)] // <--- IMPORTANT: autorise NULL
    private ?MenuItem $menuItem = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->quantite = 1;
        $this->personalisationJson = [];
    }

    // Getters / Setters
    public function getId(): ?int { return $this->id; }

    public function getQuantite(): ?int { return $this->quantite; }
    public function setQuantite(int $quantite): static
    {
        $this->quantite = max(1, $quantite);
        $this->touch();
        return $this;
    }

    public function getPrixUnitaire(): ?string { return $this->prixUnitaire; }
    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        $this->touch();
        return $this;
    }

    public function getPersonalisationJson(): ?array { return $this->personalisationJson; }
    public function setPersonalisationJson(?array $personalisationJson): static
    {
        $this->personalisationJson = $personalisationJson ?? [];
        $this->touch();
        return $this;
    }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // Relations
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        $this->touch();
        return $this;
    }

    public function getMenuItem(): ?MenuItem { return $this->menuItem; }
    public function setMenuItem(?MenuItem $menuItem): static
    {
        $this->menuItem = $menuItem;
        $this->touch();
        return $this;
    }

    // Utilitaires
    public function getPrixUnitaireFloat(): float { return (float)$this->prixUnitaire; }
    public function setPrixUnitaireFloat(float $prix): static
    {
        $this->prixUnitaire = number_format($prix, 2, '.', '');
        $this->touch();
        return $this;
    }

    public function getSousTotal(): float
    {
        return $this->getPrixUnitaireFloat() * (int)$this->quantite;
    }

    public function getFormattedSousTotal(): string
    {
        return number_format($this->getSousTotal(), 2).' DH';
    }

    public function getDisplayName(): string
    {
        $name = $this->menuItem?->getNom() ?: ($this->commentaire ?: 'Article');
        if ($this->quantite > 1) $name = "{$this->quantite}x ".$name;
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
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->getFormattedSousTotal());
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
