<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTimeImmutable;

class JWTService
{
    private string $secret;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'KaoutarImane2024'; // défini dans .env
    }

    public function generateToken(array $payload, int $expire = 3600): string
    {
        $issuedAt   = new DateTimeImmutable();
        $expireAt   = $issuedAt->modify("+{$expire} seconds");

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expireAt->getTimestamp(),
        ]);

        return JWT::encode($tokenPayload, $this->secret, 'HS256');
    }

    public function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
