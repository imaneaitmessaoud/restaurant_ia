<?php

namespace App\Entity;

use App\Repository\CustomerPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerPreferenceRepository::class)]
class CustomerPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'preference')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20, nullable: false)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredCategories = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $favoriteDishes = [];

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastOrderDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): static
    {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getPreferredCategories(): ?array
    {
        return $this->preferredCategories;
    }

    public function setPreferredCategories(?array $preferredCategories): static
    {
        $this->preferredCategories = $preferredCategories;
        return $this;
    }

    public function getFavoriteDishes(): ?array
    {
        return $this->favoriteDishes;
    }

    public function setFavoriteDishes(?array $favoriteDishes): static
    {
        $this->favoriteDishes = $favoriteDishes;
        return $this;
    }

    public function getLastOrderDate(): ?\DateTimeInterface
    {
        return $this->lastOrderDate;
    }

    public function setLastOrderDate(?\DateTimeInterface $lastOrderDate): static
    {
        $this->lastOrderDate = $lastOrderDate;
        return $this;
    }
    public function savePreference(EntityManagerInterface $em)
    {
        // Supposons que tu as un utilisateur $user (à récupérer ou créer)
        $user = $em->getRepository(User::class)->find(1); // ou un User valide

        $preference = new CustomerPreference();
        $preference->setUser($user);
        $preference->setCustomerPhone('0600000000');
        $preference->setPreferredCategories(['marocaine', 'italienne']);
        $preference->setFavoriteDishes(['tajine', 'pizza']);
        $preference->setLastOrderDate(new \DateTime());

        // PERSIST et FLUSH pour sauvegarder
        $em->persist($preference);
        $em->flush();
    }
}
