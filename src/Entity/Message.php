<?php

namespace App\Entity;

use App\Enum\SenderTypeEnum;
use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(type: 'string', enumType: SenderTypeEnum::class)]
    private SenderTypeEnum $senderType = SenderTypeEnum::CLIENT;

    #[ORM\Column]
    private ?\DateTimeImmutable $timestamp = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private ?bool $processed = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    // Relation avec Conversation
    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->timestamp = new \DateTimeImmutable();
        $this->senderType = SenderTypeEnum::CLIENT;
        $this->processed = false;
        $this->metadata = [];
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getSenderType(): SenderTypeEnum
    {
        return $this->senderType;
    }

    public function setSenderType(SenderTypeEnum $senderType): static
    {
        $this->senderType = $senderType;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function isProcessed(): ?bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): static
    {
        $this->processed = $processed;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata ?? [];
        return $this;
    }

    // Relation avec Conversation
    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;
        return $this;
    }

    // Méthodes utilitaires pour sender type
    public function isFromClient(): bool
    {
        return $this->senderType === SenderTypeEnum::CLIENT;
    }

    public function isFromBot(): bool
    {
        return $this->senderType === SenderTypeEnum::BOT;
    }

    public function isFromHuman(): bool
    {
        return $this->senderType === SenderTypeEnum::HUMAIN;
    }

    public function isAutomated(): bool
    {
        return $this->senderType->isAutomated();
    }

    // Méthodes pour les métadonnées
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function setMetadataValue(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    // Méthodes de statut
    public function markAsRead(): static
    {
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsProcessed(): static
    {
        $this->processed = true;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isUnread(): bool
    {
        return !$this->isRead();
    }

    // Méthodes utilitaires
    public function getPreview(int $length = 50): string
    {
        if (strlen($this->contenu) <= $length) {
            return $this->contenu;
        }
        return substr($this->contenu, 0, $length) . '...';
    }

    public function getWordCount(): int
    {
        return str_word_count($this->contenu);
    }

    public function getCharacterCount(): int
    {
        return mb_strlen($this->contenu);
    }

    public function containsKeywords(array $keywords): bool
    {
        $content = strtolower($this->contenu);
        foreach ($keywords as $keyword) {
            if (str_contains($content, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    public function getFormattedTimestamp(): string
    {
        return $this->timestamp->format('d/m/Y H:i:s');
    }

    public function getTimeAgo(): string
    {
        $diff = $this->timestamp->diff(new \DateTimeImmutable());
        
        if ($diff->days > 0) {
            return $diff->days . ' jour(s)';
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure(s)';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute(s)';
        } else {
            return 'À l\'instant';
        }
    }

    public function getFullInfo(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation?->getId(),
            'contenu' => $this->contenu,
            'preview' => $this->getPreview(),
            'sender_type' => $this->senderType->value,
            'sender_type_label' => $this->senderType->getLabel(),
            'is_from_client' => $this->isFromClient(),
            'is_from_bot' => $this->isFromBot(),
            'is_from_human' => $this->isFromHuman(),
            'is_automated' => $this->isAutomated(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'formatted_timestamp' => $this->getFormattedTimestamp(),
            'time_ago' => $this->getTimeAgo(),
            'is_read' => $this->isRead(),
            'is_processed' => $this->processed,
            'word_count' => $this->getWordCount(),
            'character_count' => $this->getCharacterCount(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
        ];
    }

    // toString pour le débogage
    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->senderType->getLabel(), $this->getPreview(30));
    }
}