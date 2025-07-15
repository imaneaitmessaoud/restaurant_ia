<?php

namespace App\Entity;

use App\Repository\MenuCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuCategoryRepository::class)]
#[ORM\Table(name: 'menu_categories')]
class MenuCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $ordre = 1;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relation bidirectionnelle avec MenuItem
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: MenuItem::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC', 'nom' => 'ASC'])]
    private Collection $menuItems;

    public function __construct()
    {
        $this->menuItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->actif = true;
        $this->ordre = 1;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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

    // Relations bidirectionnelles - MenuItem
    /**
     * @return Collection<int, MenuItem>
     */
    public function getMenuItems(): Collection
    {
        return $this->menuItems;
    }

    public function addMenuItem(MenuItem $menuItem): static
    {
        if (!$this->menuItems->contains($menuItem)) {
            $this->menuItems->add($menuItem);
            $menuItem->setCategory($this);
        }

        return $this;
    }

    public function removeMenuItem(MenuItem $menuItem): static
    {
        if ($this->menuItems->removeElement($menuItem)) {
            if ($menuItem->getCategory() === $this) {
                $menuItem->setCategory(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function getActiveMenuItems(): Collection
    {
        return $this->menuItems->filter(function(MenuItem $item) {
            return $item->isDisponible();
        });
    }

    public function getMenuItemsCount(): int
    {
        return $this->menuItems->count();
    }

    public function getActiveMenuItemsCount(): int
    {
        return $this->getActiveMenuItems()->count();
    }

    public function hasMenuItems(): bool
    {
        return !$this->menuItems->isEmpty();
    }

    public function getAveragePrice(): float
    {
        if ($this->menuItems->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;
        foreach ($this->menuItems as $item) {
            if ($item->isDisponible()) {
                $total += $item->getPrixFloat();
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    public function getDisplayName(): string
    {
        return $this->nom ?: 'Catégorie #' . $this->id;
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'ordre' => $this->ordre,
            'image' => $this->image,
            'actif' => $this->actif,
            'items_count' => $this->getMenuItemsCount(),
            'active_items_count' => $this->getActiveMenuItemsCount(),
            'average_price' => $this->getAveragePrice(),
            'has_items' => $this->hasMenuItems(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}