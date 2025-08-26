<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommandeController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/api/commandes', name: 'create_commande', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->em->getRepository(User::class)->find($data['userId']);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $commande = new Commande();
        $commande->setUser($user);
        $commande->setTotal($data['total'] ?? 0);

        // Enum pour statut
        $commande->setStatut(
            isset($data['statut'])
                ? StatutCommandeEnum::from($data['statut'])
                : StatutCommandeEnum::CONFIRMEE
        );

        // Enum pour type_service
        $commande->setTypeService(
            isset($data['type_service'])
                ? TypeServiceEnum::from($data['type_service'])
                : TypeServiceEnum::SUR_PLACE
        );

        $commande->setAdresseLivraison($data['adresse_livraison'] ?? null);
        $commande->setCommentaire($data['commentaire'] ?? null);
       

        $this->em->persist($commande);
        $this->em->flush();

        return $this->json([
            'id' => $commande->getId(),
            'total' => $commande->getTotal(),
            'statut' => $commande->getStatut()->value,
            'type_service' => $commande->getTypeService()->value,
        ]);
    }
}
