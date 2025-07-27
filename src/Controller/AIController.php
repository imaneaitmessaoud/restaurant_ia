<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\MenuItemRepository;

class AIController extends AbstractController
{
    #[Route('/dialogflow/menu', name: 'dialogflow_menu', methods: ['POST'])]
    public function menuFromDb(MenuItemRepository $menuItemRepository): JsonResponse
    {
        error_log('Webhook /dialogflow/menu appelé');
        $items = $menuItemRepository->findAll();

        $menu = [];
        foreach ($items as $item) {
            $categorie = $item->getCategory();
            $catName = $categorie ? $categorie->getNom() : 'Sans catégorie';

            $nom = $item->getNom();
            $prix = $item->getFormattedPrix(); // Formaté "XX.XX DH"

            if (!$catName || !$nom || !$prix) continue;

            $menu[$catName][] = [$nom, $prix];
        }

        $message = "📋 Voici notre menu :\n";
        foreach ($menu as $cat => $plats) {
            $message .= "\n🍽️ *$cat* :\n";
            foreach ($plats as [$nom, $prix]) {
                $message .= "- $nom - $prix\n";
            }
        }

        return $this->json([
            'fulfillmentText' => $message
        ]);
    }
}
