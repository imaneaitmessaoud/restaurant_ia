<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\MenuItem;
use App\Entity\User;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/commandes')]
class CommandeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // =========================
    // Helpers
    // =========================

    /**
     * Accepte name/value, insensibilité à la casse et espaces.
     * @template T of \UnitEnum
     * @param class-string<T> $enumClass
     * @param mixed $input
     * @param T $default
     * @return T
     */
    private function coerceEnum(string $enumClass, mixed $input, \UnitEnum $default): \UnitEnum
    {
        if ($input === null || $input === '') {
            return $default;
        }
        $s = is_string($input) ? trim($input) : (string)$input;

        // essayer via backing value
        if (method_exists($enumClass, 'tryFrom')) {
            if ($e = $enumClass::tryFrom($s)) return $e;
            if ($e = $enumClass::tryFrom(strtolower($s))) return $e;
            if ($e = $enumClass::tryFrom(strtoupper($s))) return $e;
        }
        // essayer via NOM du case (CONFIRMEE, SUR_PLACE...)
        foreach ($enumClass::cases() as $case) {
            if (strcasecmp($case->name, $s) === 0) {
                return $case;
            }
        }
        throw new \ValueError("Valeur invalide pour $enumClass: '$s'");
    }

    /**
     * JSON minimal pour l’écran cuisine (pas d’identités client).
     */
    private function serializeForKitchen(Commande $c): array
    {
        $items = [];
        foreach ($c->getCommandeItems() as $it) {
            $items[] = [
                'menu_item_id'        => $it->getMenuItem()?->getId(),
                'menu_item_name'      => $it->getMenuItem()?->getNom(),
                'quantite'            => $it->getQuantite(),
                'personalisation_json'=> $it->getPersonalisationJson() ?? [],
                'commentaire'         => $it->getCommentaire(),
            ];
        }

        return [
            'id'                => $c->getId(),
            'type_service'      => $c->getTypeService()->value,   // sur_place | emporter | livraison
            'adresse_livraison' => $c->getAdresseLivraison(),
            'commentaire'       => $c->getCommentaire(),          // note globale
            'created_at'        => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'items'             => $items,
        ];
    }


    // =========================
    // Créer une commande
    // =========================

    #[Route('', name: 'create_commande', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        // --- User
        $userId = $data['userId'] ?? null;
        if (!$userId) return $this->json(['error' => 'userId obligatoire'], 400);

        /** @var ?User $user */
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) return $this->json(['error' => 'Utilisateur non trouvé'], 404);

        // --- Enums robustes
        try {
            /** @var StatutCommandeEnum $statut */
            $statut = $this->coerceEnum(StatutCommandeEnum::class, $data['statut'] ?? null, StatutCommandeEnum::CONFIRMEE);
            /** @var TypeServiceEnum $typeService */
            $typeService = $this->coerceEnum(TypeServiceEnum::class, $data['type_service'] ?? null, TypeServiceEnum::SUR_PLACE);
        } catch (\ValueError $e) {
            return $this->json(['error' => 'Valeur enum invalide: '.$e->getMessage()], 400);
        }

        // --- Items
        $items = $data['items'] ?? [];
        if (!is_array($items) || !$items) {
            return $this->json(['error' => 'Le tableau items est obligatoire'], 400);
        }

        // --- Construire commande
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setStatut($statut);
        $commande->setTypeService($typeService);
        $commande->setAdresseLivraison($data['adresse_livraison'] ?? null);
        $commande->setCommentaire($data['commentaire'] ?? null);

        // Si livraison → adresse requise
        if ($typeService === TypeServiceEnum::LIVRAISON && empty($data['adresse_livraison'])) {
            return $this->json(['error' => 'adresse_livraison requise pour LIVRAISON'], 400);
        }

        // --- Lignes
        foreach ($items as $idx => $row) {
            $menuItemId = $row['menu_item_id'] ?? null;
            $qty        = max(1, (int)($row['qty'] ?? 0));
            if (!$menuItemId || $qty < 1) {
                return $this->json(['error' => "Item #$idx invalide (menu_item_id et qty>=1 requis)"], 400);
            }

            /** @var ?MenuItem $menuItem */
            $menuItem = $this->em->getRepository(MenuItem::class)->find($menuItemId);
            if (!$menuItem) return $this->json(['error' => "MenuItem $menuItemId introuvable"], 404);

            $unit = (float)$menuItem->getPrix();

            $ci = new CommandeItem();
            $ci->setCommande($commande);
            $ci->setMenuItem($menuItem);
            $ci->setQuantite($qty);
            $ci->setPrixUnitaireFloat($unit);

            if (!empty($row['personalisation_json'])) {
                // on fait confiance au client (affichage cuisine) ; pour tarification, calcule côté serveur si besoin
                $ci->setPersonalisationJson($row['personalisation_json']);
            }
            if (!empty($row['commentaire'])) {
                $ci->setCommentaire($row['commentaire']);
            }

            $this->em->persist($ci);
            $commande->addCommandeItem($ci);
        }

        // Total côté serveur
        $commande->updateTotal();

        $this->em->persist($commande);
        $this->em->flush();

        // Retourne le format "cuisine" si tu veux l’afficher directement
        return $this->json($this->serializeForKitchen($commande), 201);
    }

    // =========================
    // Lister (option: only_unread=1)
    // =========================

    #[Route('', name: 'list_commandes', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $onlyUnread = filter_var($request->query->get('only_unread', '0'), FILTER_VALIDATE_BOOLEAN);

        $qb = $this->em->getRepository(Commande::class)->createQueryBuilder('c')
            ->leftJoin('c.commandeItems', 'ci')->addSelect('ci')
            ->leftJoin('ci.menuItem', 'mi')->addSelect('mi')
            ->orderBy('c.id', 'DESC');

        if ($onlyUnread) {
            $qb->andWhere('c.luCuisine = :lu')->setParameter('lu', false);
        }

        $cmds = $qb->getQuery()->getResult();

        return $this->json(array_map([$this, 'serializeForKitchen'], $cmds));
    }


    // =========================
    // Marquer une commande comme lue (ne supprime pas)
    // =========================

    #[Route('/{id}/lu', name: 'mark_commande_read', methods: ['PATCH','OPTIONS'])]
    public function markRead(int $id): JsonResponse
    {
        $commande = $this->em->getRepository(Commande::class)->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }
        if ($commande->isLuCuisine()) {
            return $this->json(['message' => 'Déjà marquée comme lue']);
        }

        $commande->setLuCuisine(true);
        $this->em->flush();

        return $this->json(['message' => 'OK']);
    }

}
