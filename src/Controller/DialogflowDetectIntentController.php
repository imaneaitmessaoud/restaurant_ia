<?php
// src/Controller/DialogflowDetectIntentController.php
namespace App\Controller;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;

class DialogflowDetectIntentController extends AbstractController
{
    private HttpClientInterface $http;
    private string $projectId;
    private string $keyPath;

    public function __construct(HttpClientInterface $http, ParameterBagInterface $params)
    {
        $this->http = $http;
        // Lis depuis .env / .env.local
        $this->projectId = (string)($_ENV['DIALOGFLOW_PROJECT_ID'] ?? '');
        $this->keyPath   = (string)($_ENV['DIALOGFLOW_KEY_PATH'] ?? '');

        // Remplace le placeholder %kernel.project_dir% si présent
        if (str_starts_with($this->keyPath, '%kernel.project_dir%')) {
            $this->keyPath = str_replace('%kernel.project_dir%', $params->get('kernel.project_dir'), $this->keyPath);
        }
    }

    private function getAccessToken(): string
    {
        if (!$this->projectId) {
            throw new \RuntimeException('DIALOGFLOW_PROJECT_ID manquant dans .env');
        }
        if (!is_file($this->keyPath)) {
            throw new \RuntimeException("Clé Dialogflow introuvable: {$this->keyPath}");
        }

        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
        $credentials = new ServiceAccountCredentials($scopes, $this->keyPath);
        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new \RuntimeException('Impossible de récupérer un access_token Google.');
        }
        return $token['access_token'];
    }

    /**
     * Webhook Twilio/WhatsApp -> relaye le texte à Dialogflow detectIntent
     * Retourne du XML TwiML si form-urlencoded (Twilio), sinon JSON.
     */
    #[Route('/webhook', name: 'dialogflow_webhook_detect_intent', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        // 1) Récupérer message entrant
        // Cas Twilio (form-urlencoded)
        $from = $request->request->get('From');   // ex: whatsapp:+2126XXXXXXX
        $text = $request->request->get('Body');   // message texte

        // Cas JSON (tests manuels depuis Postman)
        if (!$text) {
            $json = json_decode($request->getContent(), true);
            $text = $json['text'] ?? $json['query'] ?? null;
            $from = $json['session'] ?? 'local-test';
        }

        if (!$text) {
            return $this->json(['error' => 'Aucun texte reçu (Body ou JSON.text/query)'], 400);
        }

        // 2) Appel Dialogflow detectIntent
        $sessionId = $from ?: ('session-'.bin2hex(random_bytes(4)));
        $url = sprintf(
            'https://dialogflow.googleapis.com/v2/projects/%s/agent/sessions/%s:detectIntent',
            $this->projectId,
            rawurlencode($sessionId)
        );

        $payload = [
            'queryInput' => [
                'text' => [
                    'text' => $text,
                    'languageCode' => 'fr'
                ]
            ]
        ];

        $accessToken = $this->getAccessToken();

        $apiResponse = $this->http->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload
        ]);

        $data  = $apiResponse->toArray(false);
        $reply = $data['queryResult']['fulfillmentText'] ?? "Je n’ai pas compris.";

        // 3) Répondre selon la source:
        // - Twilio attend du TwiML (application/xml) si c’était du form-urlencoded
        if ($request->request->has('Body')) {
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Message>{$reply}</Message>
</Response>
XML;
            return new Response($xml, 200, ['Content-Type' => 'application/xml']);
        }

        // - Sinon JSON (tests Postman)
        return $this->json(['reply' => $reply, 'session' => $sessionId]);
    }
}
