<?php
// src/Controller/DialogflowWebhookController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\OAuth2;

class DialogflowWebhookController extends AbstractController
{
    private $httpClient;
    private $dialogflowProjectId = 'restaurant-ai-bot-qqvx';

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }
    public function getAccessToken(): string
    {
        $keyFilePath = __DIR__ . '/config/dialogflow-key.json';

        if (!file_exists($keyFilePath)) {
            throw new \Exception("Le fichier dialogflow-key.json n'existe PAS au chemin : $keyFilePath");
        }

        $json = file_get_contents($keyFilePath);
        if ($json === false) {
            throw new \Exception("Impossible de lire le fichier dialogflow-key.json");
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Le fichier dialogflow-key.json n'est pas un JSON valide : " . json_last_error_msg());
        }

        // Puis continuer avec ta création de ServiceAccountCredentials
        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
        $credentials = new ServiceAccountCredentials($scopes, $keyFilePath);
        $authToken = $credentials->fetchAuthToken();

        return $authToken['access_token'];
    }


    #[Route('/webhook', name: 'dialogflow_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $from = $request->request->get('From');  // Exemple: whatsapp:+2126XXXXXXX
        $body = $request->request->get('Body');  // Message texte reçu

        // Appeler Dialogflow detectIntent API
        $response = $this->httpClient->request('POST', "https://dialogflow.googleapis.com/v2/projects/{$this->dialogflowProjectId}/agent/sessions/{$from}:detectIntent", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'queryInput' => [
                    'text' => [
                        'text' => $body,
                        'languageCode' => 'fr',
                    ],
                ],
            ],
        ]);

        $data = $response->toArray(false);
        $reply = $data['queryResult']['fulfillmentText'] ?? "Je n’ai pas compris.";

        // Répondre à Twilio avec XML (format TwiML)
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Message>$reply</Message>
</Response>
XML;

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
