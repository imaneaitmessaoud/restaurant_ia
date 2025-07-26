<?php
// src/Controller/ChatController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\OpenAIService;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'chat')]
    public function index(OpenAIService $openAIService): Response
    {
        $response = $openAIService->askChatGPT('Bonjour, que fais-tu ?');

        return new Response('<pre>' . $response . '</pre>');
    }
}
