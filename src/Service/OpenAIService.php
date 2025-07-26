<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAIService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $openAiApiKey)
    {
        $this->client = $client;
        $this->apiKey = $openAiApiKey;
        var_dump($this->apiKey); // pour vérifier que la clé est bien transmise

    }

    public function askChatGPT(string $prompt): string
    {
        try {
            sleep(2); // attendre 2 secondes pour éviter d'atteindre la limite
            
            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
            ]);

            $content = $response->toArray();

            return $content['choices'][0]['message']['content'] ?? 'Pas de réponse.';
        } catch (\Exception $e) {
            return "Erreur OpenAI : " . $e->getMessage();
        }
    }

}
