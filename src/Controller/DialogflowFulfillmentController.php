<?php
// src/Controller/DialogflowFulfillmentController.php
namespace App\Controller;

use App\Service\MenuExcelReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DialogflowFulfillmentController extends AbstractController
{
    public function __construct(private MenuExcelReader $reader) {}

    #[Route('/webhook/dialogflow', name: 'dialogflow_fulfillment', methods: ['POST'])]
    public function fulfill(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $intent  = $payload['queryResult']['intent']['displayName'] ?? null;

        if ($intent === 'AfficherMenu') {
            $items = $this->reader->readMenu();
            if (!$items) {
                return $this->json(['fulfillmentText' => "Désolé, le menu est momentanément indisponible."]);
            }
            $text = "📋 Voici notre menu :\n\n";
            foreach ($items as $i) {
                $text .= "🍽 {$i['name']} — {$i['price']} MAD\n";
            }
            return $this->json(['fulfillmentText' => $text]);
        }

        if ($intent === 'DemanderPrix') {
            $plat = $payload['queryResult']['parameters']['plat'] ?? null;
            if (!$plat) {
                return $this->json(['fulfillmentText' => "Quel plat vous intéresse exactement ?"]);
            }
            $items = $this->reader->readMenu();
            $found = null;
            foreach ($items as $i) {
                if (mb_strtolower($i['name']) === mb_strtolower($plat)) { $found = $i; break; }
            }
            if (!$found) {
                return $this->json(['fulfillmentText' => "Je n’ai pas trouvé “{$plat}”. Voulez-vous voir le menu complet ?"]);
            }
            return $this->json(['fulfillmentText' => "Le **{$found['name']}** coûte **{$found['price']} MAD**. Souhaitez-vous le commander ?"]);
        }

        if ($intent === 'ConseillerPlat') {
            $budgetMax = $payload['queryResult']['parameters']['montant'] ?? null;
            $items = $this->reader->readMenu();

            if ($budgetMax) {
                $suggest = array_values(array_filter($items, fn($i) => (float)$i['price'] <= (float)$budgetMax));
                if ($suggest) {
                    $txt = "Voici quelques idées à moins de {$budgetMax} MAD :\n";
                    foreach ($suggest as $s) { $txt .= "- {$s['name']} ({$s['price']} MAD)\n"; }
                    return $this->json(['fulfillmentText' => $txt]);
                }
            }
            $popular = array_slice($items, 0, 3);
            $txt = "Quelques suggestions populaires :\n";
            foreach ($popular as $p) { $txt .= "- {$p['name']} ({$p['price']} MAD)\n"; }
            return $this->json(['fulfillmentText' => $txt]);
        }

        return $this->json(['fulfillmentText' => "✅ Webhook OK : je reçois bien les requêtes."]);
    }
}
