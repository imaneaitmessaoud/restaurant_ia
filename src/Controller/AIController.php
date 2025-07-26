<?php
namespace App\Controller;

use App\Service\OpenAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MockOpenAIService;

class AIController extends AbstractController
{
    #[Route('/ask-ai', name: 'ask_ai')]
    public function ask(MockOpenAIService $openAIService): Response
    {
        $userQuestion = 'Donne-moi une idée de menu végétarien';
        $aiResponse = $openAIService->askChatGPT($userQuestion);
        return new Response($aiResponse);
    }
    /*public function ask(OpenAIService $openAIService): Response
    {
        $userQuestion = 'Donne-moi une idée de menu végétarien';

        $aiResponse = $openAIService->ask($userQuestion); // méthode correcte

        return new Response($aiResponse);
    }*/


}
