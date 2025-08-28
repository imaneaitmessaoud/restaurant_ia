<?php
// src/Service/MenuProvider.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

class MenuProvider
{
    private string $excelPath;
    private ?array $cache = null;
    private ?int $mt = null;

    public function __construct(string $projectDir)
    {
        $this->excelPath = $projectDir.'/public/uploads/config.xlsx';
    }

    public function getMenu(): array
    {
        $this->ensureLoaded();
        return $this->cache['menu'] ?? [];
    }

    public function findByName(string $name): ?array
    {
        $this->ensureLoaded();
        $needle = $this->norm($name);

        // 1) exact
        foreach ($this->cache['menu'] as $row) {
            if ($this->norm($row['name']) === $needle) return $row;
        }
        // 2) contains
        foreach ($this->cache['menu'] as $row) {
            if (str_contains($this->norm($row['name']), $needle)) return $row;
        }
        // 3) fuzzy (levenshtein)
        $best = null; $bestDist = 999;
        foreach ($this->cache['menu'] as $row) {
            $dist = levenshtein($this->norm($row['name']), $needle);
            if ($dist < $bestDist) { $bestDist = $dist; $best = $row; }
        }
        return ($bestDist <= 2) ? $best : null;
    }

    // --- lecture du Excel + cache filemtime ---
    private function ensureLoaded(): void
    {
        $mt = file_exists($this->excelPath) ? filemtime($this->excelPath) : null;
        if ($this->cache !== null && $this->mt === $mt) return;

        if (!is_file($this->excelPath)) {
            $this->cache = ['menu'=>[]]; $this->mt = null; return;
        }
        $ss = IOFactory::load($this->excelPath);

        // Feuille "Menu"
        $menu = [];
        $sheet = $ss->getSheetByName('Menu') ?? $ss->getActiveSheet();
        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) $cells[] = trim((string)$cell->getValue());
            if (!empty($cells[0])) {
                $menu[] = [
                    'name'     => $cells[0],
                    'price'    => (float)($cells[1] ?? 0),
                    'quantity' => (int)  ($cells[2] ?? 1),
                ];
            }
        }

        $this->cache = ['menu' => $menu];
        $this->mt = $mt;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower($s);
        $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
        $s = preg_replace('~[^a-z0-9]+~',' ', $s);
        $s = trim(preg_replace('~\s+~',' ', $s));
        return $s;
    }
    public function getChecksum(): ?string
  {
    return is_file($this->excelPath) ? hash_file('sha256', $this->excelPath) : null;
  }

}
