<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\MenuItemRepository;
use App\Service\DialogflowAuthVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AIController extends AbstractController
{

    #[Route('/dialogflow/menu', name: 'dialogflow_menu', methods: ['POST'])]
    public function menuFromDb(Request $request, MenuItemRepository $menuItemRepository, DialogflowAuthVerifier $authVerifier): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Authorization header missing'], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7); // Retire 'Bearer '
        if (!$authVerifier->verifyToken($token)) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        // Authentifié avec succès
        $items = $menuItemRepository->findAll();
        $menu = [];

        foreach ($items as $item) {
            $categorie = $item->getCategory();
            $catName = $categorie ? $categorie->getNom() : 'Sans catégorie';
            $nom = $item->getNom();
            $prix = $item->getFormattedPrix();

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

        return $this->json(['fulfillmentText' => $message]);
    }
        

}
