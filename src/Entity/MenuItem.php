<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'menu_items')]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $prix = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?bool $disponible = true;

    #[ORM\Column(nullable: true)]
    private ?int $ordre = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ingredients = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allergenes = null;

    #[ORM\Column(nullable: true)]
    private ?int $tempsPreparation = null; // en minutes

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relation avec MenuCategory
    #[ORM\ManyToOne(targetEntity: MenuCategory::class, inversedBy: 'menuItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MenuCategory $category = null;

    // Relations bidirectionnelles
    #[ORM\OneToMany(mappedBy: 'menuItem', targetEntity: MenuPersonalization::class, cascade: ['persist', 'remove'])]
    private Collection $personalizations;

    #[ORM\OneToMany(mappedBy: 'menuItem', targetEntity: CommandeItem::class)]
    private Collection $commandeItems;

    public function __construct()
    {
        $this->personalizations = new ArrayCollection();
        $this->commandeItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->disponible = true;
        $this->ingredients = [];
        $this->allergenes = [];
    }

    // Getters et Setters
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getIngredients(): ?array
    {
        return $this->ingredients;
    }

    public function setIngredients(?array $ingredients): static
    {
        $this->ingredients = $ingredients ?? [];
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAllergenes(): ?array
    {
        return $this->allergenes;
    }

    public function setAllergenes(?array $allergenes): static
    {
        $this->allergenes = $allergenes ?? [];
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTempsPreparation(): ?int
    {
        return $this->tempsPreparation;
    }

    public function setTempsPreparation(?int $tempsPreparation): static
    {
        $this->tempsPreparation = $tempsPreparation;
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

    // Relation avec MenuCategory
    public function getCategory(): ?MenuCategory
    {
        return $this->category;
    }

    public function setCategory(?MenuCategory $category): static
    {
        $this->category = $category;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Relations bidirectionnelles - MenuPersonalization
    /**
     * @return Collection<int, MenuPersonalization>
     */
    public function getPersonalizations(): Collection
    {
        return $this->personalizations;
    }

    public function addPersonalization(MenuPersonalization $personalization): static
    {
        if (!$this->personalizations->contains($personalization)) {
            $this->personalizations->add($personalization);
            $personalization->setMenuItem($this);
        }

        return $this;
    }

    public function removePersonalization(MenuPersonalization $personalization): static
    {
        if ($this->personalizations->removeElement($personalization)) {
            if ($personalization->getMenuItem() === $this) {
                $personalization->setMenuItem(null);
            }
        }

        return $this;
    }

    // Relations bidirectionnelles - CommandeItem
    /**
     * @return Collection<int, CommandeItem>
     */
    public function getCommandeItems(): Collection
    {
        return $this->commandeItems;
    }

    public function addCommandeItem(CommandeItem $commandeItem): static
    {
        if (!$this->commandeItems->contains($commandeItem)) {
            $this->commandeItems->add($commandeItem);
            $commandeItem->setMenuItem($this);
        }

        return $this;
    }

    public function removeCommandeItem(CommandeItem $commandeItem): static
    {
        if ($this->commandeItems->removeElement($commandeItem)) {
            if ($commandeItem->getMenuItem() === $this) {
                $commandeItem->setMenuItem(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function getPrixFloat(): float
    {
        return (float) $this->prix;
    }

    public function setPrixFloat(float $prix): static
    {
        $this->prix = (string) $prix;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFormattedPrix(): string
    {
        return number_format($this->getPrixFloat(), 2) . ' DH';
    }

    public function hasPersonalizations(): bool
    {
        return !$this->personalizations->isEmpty();
    }

    public function getTotalOrders(): int
    {
        return $this->commandeItems->count();
    }

    public function getTotalQuantityOrdered(): int
    {
        $total = 0;
        foreach ($this->commandeItems as $item) {
            $total += $item->getQuantite();
        }
        return $total;
    }

    public function isPopular(int $threshold = 10): bool
    {
        return $this->getTotalOrders() >= $threshold;
    }

    public function addIngredient(string $ingredient): static
    {
        if (!in_array($ingredient, $this->ingredients ?? [])) {
            $this->ingredients[] = $ingredient;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function removeIngredient(string $ingredient): static
    {
        $key = array_search($ingredient, $this->ingredients ?? []);
        if ($key !== false) {
            unset($this->ingredients[$key]);
            $this->ingredients = array_values($this->ingredients);
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function hasIngredient(string $ingredient): bool
    {
        return in_array($ingredient, $this->ingredients ?? []);
    }

    public function addAllergene(string $allergene): static
    {
        if (!in_array($allergene, $this->allergenes ?? [])) {
            $this->allergenes[] = $allergene;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function hasAllergene(string $allergene): bool
    {
        return in_array($allergene, $this->allergenes ?? []);
    }

    public function getEstimatedDeliveryTime(): int
    {
        return ($this->tempsPreparation ?? 15) + 5; // + 5 min packaging
    }

    public function getDisplayName(): string
    {
        return $this->nom ?: 'Article #' . $this->id;
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'prix' => $this->getPrixFloat(),
            'prix_formatted' => $this->getFormattedPrix(),
            'image' => $this->image,
            'disponible' => $this->disponible,
            'ordre' => $this->ordre,
            'ingredients' => $this->ingredients,
            'allergenes' => $this->allergenes,
            'temps_preparation' => $this->tempsPreparation,
            'estimated_delivery_time' => $this->getEstimatedDeliveryTime(),
            'category_id' => $this->category?->getId(),
            'category_name' => $this->category?->getNom(),
            'has_personalizations' => $this->hasPersonalizations(),
            'personalizations_count' => $this->personalizations->count(),
            'total_orders' => $this->getTotalOrders(),
            'total_quantity_ordered' => $this->getTotalQuantityOrdered(),
            'is_popular' => $this->isPopular(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->getFormattedPrix());
    }
}