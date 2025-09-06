<?php
namespace App\Controller;

use App\Entity\MenuItem;
use App\Entity\MenuPersonalization;
use App\Service\PersonalizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug/personalizations', name: 'debug_personalizations_')]
class DebugPersonalizationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PersonalizationService $perso
    ) {}

    /**
     * GET /debug/personalizations/by-id/{id}
     * Retourne les personnalizations brutes liées à un MenuItem par son ID.
     */
    #[Route('/by-id/{id}', name: 'by_id', methods: ['GET'])]
    public function byId(int $id): Response
    {
        $item = $this->em->getRepository(MenuItem::class)->find($id);
        if (!$item) {
            return $this->json(['ok'=>false, 'error'=>'MenuItem introuvable', 'id'=>$id], 404);
        }

        // Récupérer les MenuPersonalization actifs liés
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(MenuPersonalization::class, 'p')
            ->where('p.menuItem = :mi')
            ->andWhere('p.actif = 1')
            ->orderBy('p.ordre', 'ASC')
            ->setParameter('mi', $item);

        /** @var MenuPersonalization[] $rows */
        $rows = $qb->getQuery()->getResult();

        $list = [];
        foreach ($rows as $p) {
            $list[] = [
                'id'          => $p->getId(),
                'type'        => $p->getType()->value,
                'type_label'  => $p->getType()->getLabel(),
                'input_type'  => $p->getInputType(),
                'obligatoire' => (bool)$p->isObligatoire(),
                'prix_supplement' => (float)($p->getPrixSupplement() ?? 0),
                'options'     => $p->getOptionsJson(),
                'ordre'       => (int)$p->getOrdre(),
                'actif'       => (bool)$p->isActif(),
            ];
        }

        return $this->json([
            'ok'   => true,
            'item' => [
                'id'    => $item->getId(),
                'nom'   => $item->getNom(),
                'prix'  => (float)$item->getPrix(),
            ],
            'count' => count($list),
            'personalizations' => $list,
        ]);
    }

    /**
     * GET /debug/personalizations/by-name?name=Pizza%20Margherita
     * Même chose mais recherche par NOM d’article.
     */
    #[Route('/by-name', name: 'by_name', methods: ['GET'])]
    public function byName(Request $request): Response
    {
        $name = trim((string)$request->query->get('name', ''));
        if ($name === '') {
            return $this->json(['ok'=>false, 'error'=>'Paramètre ?name= requis'], 400);
        }

        $item = $this->em->getRepository(MenuItem::class)->findOneBy(['nom'=>$name]);
        if (!$item) {
            return $this->json(['ok'=>false, 'error'=>'MenuItem introuvable', 'name'=>$name], 404);
        }

        // Reuse byId logic
        $request->query->set('id', (string)$item->getId());
        return $this->byId($item->getId());
    }

    /**
     * GET /debug/personalizations/catalog?name=Pizza%20Margherita
     * Renvoie le "catalog" que ton PersonalizationService produit (clé, label, required, values, delta...).
     */
    #[Route('/catalog', name: 'catalog', methods: ['GET'])]
    public function catalog(Request $request): Response
    {
        $name = trim((string)$request->query->get('name', ''));
        if ($name === '') {
            return $this->json(['ok'=>false, 'error'=>'Paramètre ?name= requis'], 400);
        }

        $catalog = $this->perso->getCatalogFor($name);
        return $this->json([
            'ok' => true,
            'name' => $name,
            'count' => count($catalog),
            'catalog' => $catalog,
        ]);
    }
}
