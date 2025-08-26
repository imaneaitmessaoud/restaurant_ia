<?php

// src/Service/MenuExcelReaderService.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

class MenuExcelReaderService
{
    private string $excelPath;

    public function __construct(string $projectDir)
    {
        $this->excelPath = $projectDir . '/public/export/menu.xlsx';
    }

    public function readMenu(): array
    {
        if (!file_exists($this->excelPath)) {
            return [];
        }

        $spreadsheet = IOFactory::load($this->excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $menu = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // entête
            [$nom, $description, $prix, $disponible, $categorie] = $row;

            if (strtolower($disponible) !== 'oui') continue;

            $catName = $categorie ?: 'Sans catégorie';
            $prixFormate = number_format((float) $prix, 2) . ' DH';

            $menu[$catName][] = [$nom, $prixFormate];
        }

        return $menu;
    }
}
