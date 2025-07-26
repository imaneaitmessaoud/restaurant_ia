<?php
namespace App\Service;

class MockOpenAIService
{
    public function askChatGPT(string $prompt): string
    {
        // Tu peux personnaliser la réponse selon le prompt ou juste renvoyer un texte fixe
        return "Ceci est une réponse simulée pour le prompt : \"$prompt\"";
    }
}
