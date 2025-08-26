<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

class MenuExcelReader
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function readMenu(): array
    {
        $filePath = $this->projectDir . '/public/uploads/config.xlsx';

        if (!file_exists($filePath)) {
            throw new \Exception("Le fichier menu.xlsx est introuvable à : " . $filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $menu = [];
        foreach ($sheet->getRowIterator(2) as $row) { // ligne 1 = en-têtes
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            // Exemple : colonnes (A: nom, B: prix, C: quantité)
            if (!empty($cells[0])) {
                $menu[] = [
                    'name' => $cells[0],
                    'price' => $cells[1] ?? 0,
                    'quantity' => $cells[2] ?? 1,
                ];
            }
        }

        return $menu;
    }
}
