<?php

namespace App\Controller;

use App\Service\AiOrderAgent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GroqOrderAgent;
class AiTestController extends AbstractController
{
    #[Route('/groq/test', methods: ['GET'])]
    public function groqTest(GroqOrderAgent $groq): JsonResponse
    {
        $out = $groq->infer([
            'from' => '+212600000000',
            'user_text' => 'Salam, bghit pizza Margherita x2',
            'menu' => [
                ['name' => 'Pizza Margherita', 'price' => 45],
                ['name' => 'Tacos Poulet', 'price' => 35],
            ],
            'system_prompt' => 'Tu es un agent de commande restaurant.'
        ]);
        return new JsonResponse($out);
    }

    #[Route('/ai/test', methods: ['GET'])]
    public function aiTest(AiOrderAgent $ai): JsonResponse
    {
        $out = $ai->infer([
            'system_prompt' => 'Tu es un agent de commande restaurant.',
            'from' => '+212600000000',
            'user_text' => 'Salam bghit pizza Margherita x2',
            'menu' => [],
        ]);

        return new JsonResponse($out);
    }
}
