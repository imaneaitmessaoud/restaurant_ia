<?php
namespace App\Controller;

use App\Service\AiOrderAgentTools;
use App\Service\OrderPayloadGuard;
use App\Service\AiOrderMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AiToolsTestController extends AbstractController
{
    #[Route('/ai/test-tools', methods: ['GET'])]
    public function test(
        AiOrderAgentTools $ai,
        OrderPayloadGuard $guard,
        AiOrderMapper $mapper,
        EntityManagerInterface $em
    ): JsonResponse {
        $menu = \App\Service\AiOrderAgentTools::defaultMenu();

        $out = $ai->infer([
            'from' => 'whatsapp:+212600000000',
            'user_text' => 'Salam, bghit Pizza Margherita x2 grande w Coca.',
            'state' => null,
            'menu' => $menu,
            'catalogs' => [],
            'system_prompt' => 'Tu es un agent de commande restaurant. Réponds brièvement.'
        ]);

        // garde-fou (ne sauvegarde que si confirmé et complet)
        $errors = $guard->validate($out);
        if ($errors) {
            return new JsonResponse([
                'ok' => false,
                'assistant_text' => $out['assistant_text'] ?? null,
                'errors' => $errors,
                'payload' => $out
            ]);
        }

        // sauvegarde
        $cmd = $mapper->buildCommandeFromPayload($out);
        $em->persist($cmd);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'assistant_text' => $out['assistant_text'] ?? null,
            'commande' => $cmd->getFullInfo(),
            'items' => array_map(fn($ci)=>$ci->getFullInfo(), $cmd->getCommandeItems()->toArray()),
            'raw' => $out
        ]);
    }
}
