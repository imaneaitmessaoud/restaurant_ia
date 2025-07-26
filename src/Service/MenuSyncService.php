<?php

namespace App\Service;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class MenuSyncService
{
    private EntityManagerInterface $em;
    private string $excelPath;

    public function __construct(EntityManagerInterface $em, string $projectDir)
    {
        $this->em = $em;
        $this->excelPath = $projectDir . '/public/uploads/menu.xlsx';
    }

    public function importFromExcel(): void
    {
        if (!file_exists($this->excelPath)) {
            throw new \Exception("Fichier Excel non trouvé : " . $this->excelPath);
        }

        $spreadsheet = IOFactory::load($this->excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Ignorer l'entête

            [$nom, $description, $prix, $disponible] = $row;

            $menuItem = new MenuItem();
            $menuItem->setNom($nom);
            $menuItem->setDescription($description);
            $menuItem->setPrix((float)$prix);
            $menuItem->setDisponible(strtolower($disponible) === 'oui');

            $this->em->persist($menuItem);
        }

        $this->em->flush();
    }

    public function exportToExcel(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $menuItems = $this->em->getRepository(MenuItem::class)->findAll();

        // Entête
        $sheet->fromArray(['Nom', 'Description', 'Prix', 'Disponible'], null, 'A1');

        $i = 2;
        foreach ($menuItems as $item) {
            $sheet->setCellValue('A' . $i, $item->getNom());
            $sheet->setCellValue('B' . $i, $item->getDescription());
            $sheet->setCellValue('C' . $i, $item->getPrix());
            $sheet->setCellValue('D' . $i, $item->isDisponible() ? 'Oui' : 'Non');
            $i++;
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($this->excelPath);
    }
}
