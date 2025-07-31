<?php

namespace App\Controller;

use App\Repository\MenuItemRepository;
use App\Service\DialogflowAuthVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{


    #[Route('/webhook/whatsapp', name: 'whatsapp_webhook', methods: ['GET', 'POST'])]
    public function webhook(Request $request, MenuItemRepository $menuItemRepository, DialogflowAuthVerifier $authVerifier): Response
    {
        // Verification GET (Meta webhook)
        if ($request->getMethod() === 'GET') {
            $mode = $request->query->get('hub_mode');
            $token = $request->query->get('hub_verify_token');
            $challenge = $request->query->get('hub_challenge');

            if ($mode === 'subscribe' && $token === 'monTokenSecretWebhook123') {
                return new Response($challenge, 200);
            } else {
                return new Response('Token invalide', 403);
            }
        }

        // POST

        // 1. Cas Twilio WhatsApp envoie form-urlencoded avec 'Body'
        if ($request->request->has('Body')) {
            $incomingMsg = $request->request->get('Body');

            // TODO: ici appeler Dialogflow via API DetectIntent avec $incomingMsg, récupérer $responseText

            // Pour l'exemple, appelons le menu statique
            if (str_contains(strtolower($incomingMsg), 'menu')) {
                $items = $menuItemRepository->findAll();
                $menu = [];
                foreach ($items as $item) {
                    $cat = $item->getCategory();
                    $catName = $cat ? $cat->getNom() : 'Sans catégorie';
                    $nom = $item->getNom();
                    $prix = $item->getFormattedPrix();

                    if (!$catName || !$nom || !$prix) continue;
                    $menu[$catName][] = [$nom, $prix];
                }

                $responseText = "📋 Voici notre menu :\n";
                foreach ($menu as $cat => $plats) {
                    $responseText .= "\n🍽️ *$cat* :\n";
                    foreach ($plats as [$nom, $prix]) {
                        $responseText .= "- $nom - $prix\n";
                    }
                }
            } else {
                $responseText = "Je n'ai pas compris, peux-tu répéter ?";
            }

            $twiml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <Response>
        <Message>{$responseText}</Message>
    </Response>
    XML;

            return new Response($twiml, 200, ['Content-Type' => 'text/xml']);
        }

        // 2. Sinon, cas Dialogflow webhook envoie JSON
        $data = json_decode($request->getContent(), true);

        if (isset($data['queryResult']['intent']['displayName']) && $data['queryResult']['intent']['displayName'] === 'AfficherMenu') {
            $items = $menuItemRepository->findAll();
            $menu = [];

            foreach ($items as $item) {
                $cat = $item->getCategory();
                $catName = $cat ? $cat->getNom() : 'Sans catégorie';
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

        return new Response('EVENT_RECEIVED', 200);
    }

}
