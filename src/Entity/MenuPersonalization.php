<?php

namespace App\Entity;

use App\Enum\PersonalizationTypeEnum;
use App\Repository\MenuPersonalizationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuPersonalizationRepository::class)]
#[ORM\Table(name: 'menu_personalizations')]
class MenuPersonalization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: PersonalizationTypeEnum::class)]
    private PersonalizationTypeEnum $type = PersonalizationTypeEnum::TAILLE;

    #[ORM\Column(type: Types::JSON)]
    private array $optionsJson = [];

    #[ORM\Column]
    private ?bool $obligatoire = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $prixSupplement = null;

    #[ORM\Column]
    private ?int $ordre = 1;

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relation avec MenuItem
    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'personalizations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MenuItem $menuItem = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->type = PersonalizationTypeEnum::TAILLE;
        $this->optionsJson = [];
        $this->obligatoire = false;
        $this->actif = true;
        $this->ordre = 1;
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): PersonalizationTypeEnum
    {
        return $this->type;
    }

    public function setType(PersonalizationTypeEnum $type): static
    {
        $this->type = $type;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOptionsJson(): array
    {
        return $this->optionsJson;
    }

    public function setOptionsJson(array $optionsJson): static
    {
        $this->optionsJson = $optionsJson;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isObligatoire(): ?bool
    {
        return $this->obligatoire;
    }

    public function setObligatoire(bool $obligatoire): static
    {
        $this->obligatoire = $obligatoire;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPrixSupplement(): ?string
    {
        return $this->prixSupplement;
    }

    public function setPrixSupplement(?string $prixSupplement): static
    {
        $this->prixSupplement = $prixSupplement;
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

    // Relation avec MenuItem
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
    public function getPrixSupplementFloat(): float
    {
        return (float) ($this->prixSupplement ?? 0);
    }

    public function setPrixSupplementFloat(?float $prix): static
    {
        $this->prixSupplement = $prix !== null ? (string) $prix : null;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFormattedPrixSupplement(): string
    {
        $prix = $this->getPrixSupplementFloat();
        return $prix > 0 ? '+' . number_format($prix, 2) . ' DH' : 'Gratuit';
    }

    public function addOption(string $key, mixed $value): static
    {
        $this->optionsJson[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function removeOption(string $key): static
    {
        if (isset($this->optionsJson[$key])) {
            unset($this->optionsJson[$key]);
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getOption(string $key): mixed
    {
        return $this->optionsJson[$key] ?? null;
    }

    public function hasOption(string $key): bool
    {
        return isset($this->optionsJson[$key]);
    }

    public function getOptionsCount(): int
    {
        return count($this->optionsJson);
    }

    public function getInputType(): string
    {
        return $this->type->getLabel();
    }

    public function isMultipleChoice(): bool
    {
        return $this->getInputType() === 'checkbox';
    }

    public function isSingleChoice(): bool
    {
        return in_array($this->getInputType(), ['radio', 'select']);
    }

    public function validateChoice(mixed $choice): bool
    {
        if ($this->isMultipleChoice()) {
            // Pour les checkboxes, $choice doit être un array
            if (!is_array($choice)) {
                return false;
            }
            foreach ($choice as $item) {
                if (!isset($this->optionsJson[$item])) {
                    return false;
                }
            }
            return true;
        } else {
            // Pour radio/select, $choice doit être une clé valide
            return isset($this->optionsJson[$choice]);
        }
    }

    public function calculatePrice(mixed $choice): float
    {
        $basePrice = $this->getPrixSupplementFloat();
        
        if ($this->isMultipleChoice() && is_array($choice)) {
            // Pour les options multiples, prix par option
            return $basePrice * count($choice);
        }
        
        return $basePrice;
    }

    public function getDisplayName(): string
    {
        return $this->type->getLabel();
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'menu_item_id' => $this->menuItem?->getId(),
            'menu_item_name' => $this->menuItem?->getNom(),
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'input_type' => $this->getInputType(),
            'options' => $this->optionsJson,
            'options_count' => $this->getOptionsCount(),
            'obligatoire' => $this->obligatoire,
            'prix_supplement' => $this->getPrixSupplementFloat(),
            'prix_supplement_formatted' => $this->getFormattedPrixSupplement(),
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'is_multiple_choice' => $this->isMultipleChoice(),
            'is_single_choice' => $this->isSingleChoice(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->menuItem?->getNom() ?? 'Sans article');
    }
}