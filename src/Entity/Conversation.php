<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversations')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'active';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $context = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastMessageAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    // Relation optionnelle avec User (peut être null pour clients non inscrits)
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    // Relation bidirectionnelle avec Message
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->statut = 'active';
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $this->normalizePhoneNumber($phoneNumber);
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        $this->updatedAt = new \DateTimeImmutable();
        
        // Mettre à jour closedAt si fermée
        if ($statut === 'closed' && !$this->closedAt) {
            $this->closedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;
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

    public function getLastMessageAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?\DateTimeImmutable $lastMessageAt): static
    {
        $this->lastMessageAt = $lastMessageAt;
        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
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

    // Relations bidirectionnelles - Message
    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
            $this->updateLastMessageAt($message->getCreatedAt());
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    private function normalizePhoneNumber(string $phone): string
    {
        // Nettoyer et normaliser le numéro
        $phone = preg_replace('/[^+\d]/', '', $phone);
        
        // Si commence par 0, remplacer par +212
        if (str_starts_with($phone, '0')) {
            $phone = '+212' . substr($phone, 1);
        }
        
        // Si commence par 6 ou 7, ajouter +212
        if (preg_match('/^[67]/', $phone)) {
            $phone = '+212' . $phone;
        }
        
        return $phone;
    }

    public function updateLastMessageAt(?\DateTimeImmutable $timestamp = null): static
    {
        $this->lastMessageAt = $timestamp ?: new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastMessage(): ?Message
    {
        if ($this->messages->isEmpty()) {
            return null;
        }
        
        // Récupérer le dernier message
        $lastMessage = null;
        foreach ($this->messages as $message) {
            if (!$lastMessage || $message->getCreatedAt() > $lastMessage->getCreatedAt()) {
                $lastMessage = $message;
            }
        }
        
        return $lastMessage;
    }

    public function getMessagesCount(): int
    {
        return $this->messages->count();
    }

    public function getClientMessages(): Collection
    {
        return $this->messages->filter(function(Message $message) {
            return $message->isFromClient();
        });
    }

    public function getBotMessages(): Collection
    {
        return $this->messages->filter(function(Message $message) {
            return $message->isFromBot();
        });
    }

    public function getHumanMessages(): Collection
    {
        return $this->messages->filter(function(Message $message) {
            return $message->isFromHuman();
        });
    }

    // Méthodes de statut
    public function isActive(): bool
    {
        return $this->statut === 'active';
    }

    public function isClosed(): bool
    {
        return $this->statut === 'closed';
    }

    public function isPaused(): bool
    {
        return $this->statut === 'paused';
    }

    public function close(): static
    {
        return $this->setStatut('closed');
    }

    public function pause(): static
    {
        return $this->setStatut('paused');
    }

    public function activate(): static
    {
        return $this->setStatut('active');
    }

    // Méthodes d'identification du client
    public function getDisplayName(): string
    {
        if ($this->clientName) {
            return $this->clientName;
        }
        
        if ($this->user) {
            return $this->user->getNom();
        }
        
        return $this->phoneNumber ?: 'Client anonyme';
    }

    public function getClientType(): string
    {
        return $this->user ? 'registered' : 'guest';
    }

    public function isRegisteredClient(): bool
    {
        return $this->user !== null;
    }

    public function isGuestClient(): bool
    {
        return $this->user === null;
    }

    // Méthodes temporelles
    public function getDurationInMinutes(): ?int
    {
        if (!$this->createdAt) {
            return null;
        }
        
        $endTime = $this->closedAt ?: new \DateTimeImmutable();
        return $this->createdAt->diff($endTime)->i + ($this->createdAt->diff($endTime)->h * 60);
    }

    public function getTimeSinceLastMessage(): ?int
    {
        if (!$this->lastMessageAt) {
            return null;
        }
        
        return $this->lastMessageAt->diff(new \DateTimeImmutable())->i;
    }

    public function isInactive(int $minutesThreshold = 30): bool
    {
        $timeSince = $this->getTimeSinceLastMessage();
        return $timeSince !== null && $timeSince > $minutesThreshold;
    }

    public function getReference(): string
    {
        return sprintf('CONV-%06d', $this->id);
    }

    public function getFullInfo(): array
    {
        $lastMessage = $this->getLastMessage();
        
        return [
            'id' => $this->id,
            'reference' => $this->getReference(),
            'phone_number' => $this->phoneNumber,
            'client_name' => $this->clientName,
            'display_name' => $this->getDisplayName(),
            'statut' => $this->statut,
            'context' => $this->context,
            'user_id' => $this->user?->getId(),
            'client_type' => $this->getClientType(),
            'is_registered' => $this->isRegisteredClient(),
            'messages_count' => $this->getMessagesCount(),
            'last_message_preview' => $lastMessage?->getContenu() ? substr($lastMessage->getContenu(), 0, 50) . '...' : null,
            'last_message_at' => $this->lastMessageAt?->format('Y-m-d H:i:s'),
            'duration_minutes' => $this->getDurationInMinutes(),
            'time_since_last_message' => $this->getTimeSinceLastMessage(),
            'is_inactive' => $this->isInactive(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'closed_at' => $this->closedAt?->format('Y-m-d H:i:s'),
        ];
    }

    // toString pour le débogage
    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getDisplayName(), $this->phoneNumber);
    }
}