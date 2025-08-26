<?php

namespace App\Service;

use App\Entity\MenuItem;
use App\Entity\MenuCategory;
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
        $this->excelPath = $projectDir . '/public/uploads/config.xlsx';
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
            if ($index === 0) continue; // Ignorer l’entête

            [$id, $nom, $description, $prix, $categorieNom] = $row;

            if (!$nom || !$prix || !$categorieNom) {
                continue; // ignore les lignes incomplètes
            }

            // Vérifier ou créer la catégorie
            $categorie = $this->em->getRepository(MenuCategory::class)->findOneBy(['nom' => $categorieNom]);
            if (!$categorie) {
                $categorie = new MenuCategory();
                $categorie->setNom($categorieNom);
                $this->em->persist($categorie);
            }

            // Vérifier si un plat avec le même nom existe déjà (éviter doublons)
            $menuItem = $this->em->getRepository(MenuItem::class)->findOneBy(['nom' => $nom]);
            if (!$menuItem) {
                $menuItem = new MenuItem();
            }

            $menuItem->setNom($nom);
            $menuItem->setDescription($description);
            $menuItem->setPrix((float)$prix);
            $menuItem->setDisponible(true); // par défaut disponible
            $menuItem->setCategory($categorie);

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
        $sheet->fromArray(['ID', 'Nom du plat', 'Description', 'Prix', 'Catégorie'], null, 'A1');

        $i = 2;
        foreach ($menuItems as $item) {
            $sheet->setCellValue('A' . $i, $item->getId());
            $sheet->setCellValue('B' . $i, $item->getNom());
            $sheet->setCellValue('C' . $i, $item->getDescription());
            $sheet->setCellValue('D' . $i, $item->getPrix());
            $sheet->setCellValue('E' . $i, $item->getCategory()?->getNom());
            $i++;
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($this->excelPath);
    }
}
