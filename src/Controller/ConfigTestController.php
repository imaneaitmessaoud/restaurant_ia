<?php
namespace App\Controller;

use App\Service\MenuProvider;
use App\Service\InfoProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;      

class ConfigTestController extends AbstractController
{
    public function __construct(
        private MenuProvider $menu,
        private InfoProvider $info
    ) {}

    #[Route('/config/test/menu', name: 'config_test_menu', methods: ['GET'])]
    public function menu(): JsonResponse
    {
        return $this->json($this->menu->getMenu());
    }

   #[Route('/config/test/infos', name: 'config_test_infos', methods: ['GET'])]
public function infos(InfoProvider $info): Response
{
    $lines = [];
    $lines[] = $info->intro();          // intro avec nom + adresse
    $lines[] = "";
    $lines[] = $info->horaires();       // horaires formatés
    $lines[] = "";
    $lines[] = $info->promos();         // promos formatées
    $lines[] = "";
    $lines[] = $info->services();       // services formatés
    $lines[] = "";
    $lines[] = $info->livraisonZones(); // zones livraison formatées

    $text = implode("\n\n", $lines);

    return new Response($text, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8'
    ]);
}

    #[Route('/config/test/horaires', name: 'config_test_horaires', methods: ['GET'])]
public function horaires(InfoProvider $info): Response
{
    return new Response($info->horaires(), 200, [
        'Content-Type' => 'text/plain; charset=UTF-8'
    ]);
}



    #[Route('/config/test/promos', name: 'config_test_promos', methods: ['GET'])]
    public function promos(): JsonResponse
    {
        return $this->json($this->info->getPromos());
    }

    #[Route('/config/test/livraison', name: 'config_test_livraison', methods: ['GET'])]
    public function livraison(): JsonResponse
    {
        return $this->json($this->info->getLivraison());
    }

    #[Route('/config/test/services', name: 'config_test_services', methods: ['GET'])]
    public function services(): JsonResponse
    {
        return $this->json($this->info->getServices());
    }
}
