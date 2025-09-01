<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MenuItemController extends AbstractController
{
    public function __construct(
        private MenuItemRepository $repo,
        private EntityManagerInterface $em
    ) {}

    // ===== LIST / SEARCH =====
    #[Route('/api/menu-items', name: 'menu_item_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string)$request->query->get('q', ''));
        $items = $q ? $this->repo->searchByName($q) : $this->repo->findAvailableItems();

        // mapping light (évite hydratation relations sensibles)
        $payload = array_map(function (MenuItem $i) {
            return [
                'id'            => $i->getId(),
                'nom'           => $i->getNom(),
                'description'   => $i->getDescription(),
                'prix'          => $i->getPrix(),
                'disponible'    => $i->isDisponible(),
                'ordre'         => $i->getOrdre(),
                'image'         => $i->getImage(),
                'category_id'   => $i->getCategory()?->getId(),
                'category_name' => $i->getCategory()?->getNom(),
            ];
        }, $items);

        return new JsonResponse($payload, 200);
    }

    // ===== ADD =====
    #[Route('/api/menu-items', name: 'menu_item_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $item = new MenuItem();
            $item->setNom($request->request->get('nom', 'Sans nom'));
            $item->setDescription($request->request->get('description'));
            $item->setPrix((float)$request->request->get('prix', 0));
            $item->setDisponible(true);
            $item->setOrdre(1);

            if ($categoryId = $request->request->get('category_id')) {
                $category = $this->em->getRepository(\App\Entity\MenuCategory::class)->find($categoryId);
                $item->setCategory($category);
            }

            if ($file = $request->files->get('image')) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/images';
                $newFilename = uniqid().'.'.$file->guessExtension();
                $file->move($uploadsDir, $newFilename);
                $item->setImage($newFilename);
            }

            $this->em->persist($item);
            $this->em->flush();

            return new JsonResponse(['message' => 'MenuItem ajouté'], 201);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ===== UPDATE =====
    #[Route('/api/menu-items/{id}', name: 'menu_item_update', methods: ['PUT','POST'])]
    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $data = $request->request->all();
        if (empty($data)) $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['nom']))        $menuItem->setNom($data['nom']);
        if (isset($data['description'])) $menuItem->setDescription($data['description']);
        if (isset($data['prix']))        $menuItem->setPrix((float)$data['prix']);

        if (!empty($data['category_id'])) {
            $category = $this->em->getRepository(\App\Entity\MenuCategory::class)->find($data['category_id']);
            if ($category) $menuItem->setCategory($category);
        }

        if ($file = $request->files->get('image')) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/images';
            $filename = uniqid().'.'.$file->guessExtension();
            $file->move($uploadsDir, $filename);
            $menuItem->setImage($filename);
        }

        $this->em->flush();
        return new JsonResponse(['message' => 'Item mis à jour avec succès !']);
    }

    // ===== DELETE =====
    #[Route('/api/menu-items/{id}', name: 'menu_item_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->repo->find($id);
        if (!$item) return new JsonResponse(['error' => 'MenuItem non trouvé'], 404);

        $this->em->remove($item);
        $this->em->flush();
        return new JsonResponse(['message' => 'MenuItem supprimé']);
    }
}
