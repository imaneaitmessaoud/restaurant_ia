<?php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Repository\MenuItemRepository;
use Symfony\Component\HttpKernel\KernelInterface;

class MenuExportService
{
    private $menuItemRepository;
    private $projectDir;

    // Injecte KernelInterface pour récupérer le dossier racine du projet
    public function __construct(MenuItemRepository $menuItemRepository, KernelInterface $kernel)
    {
        $this->menuItemRepository = $menuItemRepository;
        $this->projectDir = $kernel->getProjectDir();
    }

    public function exportToExcel(string $relativeFilePath = 'public/export/menu.xlsx'): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Nom');
        $sheet->setCellValue('B1', 'Prix');

        $row = 2;
        foreach ($this->menuItemRepository->findAll() as $menuItem) {
            $sheet->setCellValue('A' . $row, $menuItem->getNom());
            $sheet->setCellValue('B' . $row, $menuItem->getPrix());
            $row++;
        }

        // Construction chemin absolu
        $filePath = $this->projectDir . DIRECTORY_SEPARATOR . $relativeFilePath;

        // Crée le dossier s'il n'existe pas
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }
}
