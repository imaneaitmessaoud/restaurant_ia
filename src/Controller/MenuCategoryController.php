<?php

namespace App\Controller;

use App\Entity\MenuCategory;
use App\Repository\MenuCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/categories')]
class MenuCategoryController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $category = new MenuCategory();
        $category->setNom($data['nom'] ?? 'Sans nom');
        $category->setDescription($data['description'] ?? null);
        $category->setOrdre($data['ordre'] ?? 1);
        $category->setImage($data['image'] ?? null);
        $category->setActif($data['actif'] ?? true);

        $em->persist($category);
        $em->flush();

        return new JsonResponse([
            'message' => 'Catégorie ajoutée',
            'category' => $category->getFullInfo(),
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request, MenuCategoryRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $category = $repo->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Catégorie non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) $category->setNom($data['nom']);
        if (isset($data['description'])) $category->setDescription($data['description']);
        if (isset($data['ordre'])) $category->setOrdre($data['ordre']);
        if (isset($data['image'])) $category->setImage($data['image']);
        if (isset($data['actif'])) $category->setActif($data['actif']);

        $em->flush();

        return new JsonResponse([
            'message' => 'Catégorie mise à jour',
            'category' => $category->getFullInfo(),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, MenuCategoryRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $category = $repo->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Catégorie non trouvée'], 404);
        }

        $em->remove($category);
        $em->flush();

        return new JsonResponse(['message' => 'Catégorie supprimée']);
    }

    #[Route('', methods: ['GET'])]
    public function list(MenuCategoryRepository $repo): JsonResponse
    {
        $categories = $repo->findAll();

        return new JsonResponse(array_map(fn($cat) => $cat->getFullInfo(), $categories));
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, MenuCategoryRepository $repo): JsonResponse
    {
        $q = $request->query->get('q', null); // récupère ?q=...

        if ($q) {
            $categories = $repo->createQueryBuilder('c')
                ->where('LOWER(c.nom) LIKE :q OR LOWER(c.description) LIKE :q')
                ->setParameter('q', '%' . strtolower($q) . '%')
                ->getQuery()
                ->getResult();
        } else {
            $categories = $repo->findAll();
        }

        return new JsonResponse(array_map(fn($cat) => $cat->getFullInfo(), $categories));
    }

}
