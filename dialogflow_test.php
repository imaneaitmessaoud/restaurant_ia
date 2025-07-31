<?php
require 'vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use Symfony\Component\HttpClient\HttpClient;

$scopes = ['https://www.googleapis.com/auth/cloud-platform'];
$keyFilePath = __DIR__ . '/config/restaurant-ai-bot-qqvx-973cc05cd5e6.json';  // adapte ce chemin si besoin

$credentials = new ServiceAccountCredentials($scopes, $keyFilePath);
$token = $credentials->fetchAuthToken()['access_token'];

$httpClient = HttpClient::create();

$response = $httpClient->request('POST', 'https://dialogflow.googleapis.com/v2/projects/restaurant-ai-bot-qqvx/agent/sessions/test_session:detectIntent', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'queryInput' => [
            'text' => [
                'text' => 'Montre-moi le menu',         // <== C’est ici que tu écris le message à envoyer à Dialogflow
                'languageCode' => 'fr',
            ],
        ],
    ],
    'timeout' => 10,
]);

echo $response->getContent();
