<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/menu-items')]
class MenuItemController extends AbstractController
{
    private MenuItemRepository $repo;
    private EntityManagerInterface $em;

    public function __construct(MenuItemRepository $repo, EntityManagerInterface $em)
    {
        $this->repo = $repo;
        $this->em = $em;
    }

    
    // ===================== LIST / SEARCH =====================
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if ($q) {
            $items = $this->repo->searchByName($q);
        } else {
            $items = $this->repo->findAvailableItems();
        }

        return new JsonResponse(array_map(fn($i) => $i->getFullInfo(), $items));
    }

    // ===================== ADD =====================
    #[Route('', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $item = new MenuItem();
            $item->setNom($request->request->get('nom', 'Sans nom'));
            $item->setDescription($request->request->get('description'));
            $item->setPrix($request->request->get('prix', 0));
            $item->setDisponible(true);
            $item->setOrdre(1);

            // Catégorie
            $categoryId = $request->request->get('category_id');
            if ($categoryId) {
                $category = $this->em->getRepository('App\Entity\MenuCategory')->find($categoryId);
                $item->setCategory($category);
            }

            // IMAGE UPLOAD
            $file = $request->files->get('image');
            if ($file) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/images';
                $newFilename = uniqid().'.'.$file->guessExtension();
                $file->move($uploadsDir, $newFilename);
                $item->setImage($newFilename);
            }

            $this->em->persist($item);
            $this->em->flush();

            return new JsonResponse(['message' => 'MenuItem ajouté', 'item' => $item->getFullInfo()], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }


    // ===================== UPDATE =====================
    #[Route('/{id}', name: 'update_item', methods: ['PUT', 'POST'])]
    public function update(Request $request, MenuItem $menuItem, EntityManagerInterface $em): JsonResponse
    {
        // Récupération des données : on essaye d'abord le form-data, sinon JSON
        $data = $request->request->all(); // form-data
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        // Mise à jour des champs si présents
        if (isset($data['nom'])) {
            $menuItem->setNom($data['nom']);
        }
        if (isset($data['description'])) {
            $menuItem->setDescription($data['description']);
        }
        if (isset($data['prix'])) {
            $menuItem->setPrix((float) $data['prix']);
        }

        if (!empty($data['category_id'])) {
            $category = $em->getRepository(\App\Entity\MenuCategory::class)->find($data['category_id']);
            if ($category) {
                $menuItem->setCategory($category);
            }
        }

        // Gestion de l'image si fournie
        $file = $request->files->get('image');
        if ($file) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/images';
            $filename = uniqid() . '.' . $file->guessExtension();
            $file->move($uploadsDir, $filename);
            $menuItem->setImage($filename);
        }

        $em->flush();

        return new JsonResponse(['message' => 'Item mis à jour avec succès !', 'item' => $menuItem->getFullInfo()]);
    }


    // ===================== DELETE =====================
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->repo->find($id);
        if (!$item) {
            return new JsonResponse(['error' => 'MenuItem non trouvé'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return new JsonResponse(['message' => 'MenuItem supprimé']);
    }
}
