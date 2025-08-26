<?php

namespace App\Controller;

use App\Service\MenuExcelReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends AbstractController
{
    private MenuExcelReader $reader;

    public function __construct(MenuExcelReader $reader)
    {
        $this->reader = $reader;
    }

    #[Route('/menu', name: 'menu_text')]
    public function menuFromExcel(): Response
    {
        $items = $this->reader->readMenu();

        $output = "📋 Voici notre menu : \n\n";
        foreach ($items as $item) {
            $output .= "🍽 {$item['name']} : {$item['price']} - {$item['quantity']} MAD\n";
        }

        return new Response($output);
    }

    #[Route('/api/menu', name: 'menu_json')]
    public function menuFromExcelJson(): JsonResponse
    {
        $items = $this->reader->readMenu();

        return new JsonResponse($items);
    }
}
