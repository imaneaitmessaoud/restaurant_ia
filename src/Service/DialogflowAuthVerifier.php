<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DialogflowAuthVerifier
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function verifyToken(string $token): bool
    {
        try {
            $response = $this->client->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['access_token' => $token],
            ]);

            $data = $response->toArray();

            // Vérifie que l'audience (aud) correspond à ton client_id
            return isset($data['aud']) && $data['aud'] === $_ENV['GOOGLE_CLIENT_ID'];
        } catch (\Exception $e) {
            return false;
        }
    }
}
