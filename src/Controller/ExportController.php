<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MenuExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExportController extends AbstractController
{
    #[Route('/export-menu', name: 'export_menu')]
    public function exportMenu(MenuExportService $exportService): Response
    {
        // Exporter la dernière version depuis la base de données
        $exportService->exportToExcel();

        // Retourner une réponse pour indiquer que l'export a été effectué
        return new Response('Menu exporté avec succès.');
    }
    #[Route('/read-menu', name: 'read_menu')]
    public function readMenu(MenuExportService $exportService): Response
    {
        // Exporter la dernière version depuis la base de données
        $exportService->exportToExcel();

        // Lire le fichier Excel fraîchement exporté
        $filePath = $this->getParameter('kernel.project_dir') . '/public/export/menu.xlsx';

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier Excel introuvable.');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $data = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = $cell->getValue();
            }
            $data[] = $rowData;
        }

        return $this->render('menu/read.html.twig', [
            'data' => $data,
        ]);
    }


}
