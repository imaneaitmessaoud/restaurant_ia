<?php
// src/Service/InfoProvider.php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

class InfoProvider
{
    private string $excelPath;
    private ?array $cache = null;
    private ?int $mt = null;

    public function __construct(string $projectDir)
    {
        $this->excelPath = $projectDir.'/public/uploads/config.xlsx';
    }

    public function intro(): string
    {
        $cfg = $this->readAll();
        $name = $cfg['infos']['restaurant_name'] ?? 'Notre restaurant';
        $addr = $cfg['infos']['address'] ?? 'Adresse non définie';

        return "Bonjour ! Avec plaisir, je vous présente *{$name}* 🏛\n\n".
               "📍 *LOCALISATION*\n- {$addr}\n\n".
               "Sur quoi souhaitez-vous plus de détails ?\n".
               "1️⃣ Horaires\n2️⃣ Voir le menu\n3️⃣ Promotions\n4️⃣ Réserver\n5️⃣ Services";
    }
    public function getInfos(): array
{
    $cfg = $this->readAll();
    return [
        'infos'     => $cfg['infos']     ?? [],
        'horaires'  => $cfg['horaires']  ?? [],
        'promos'    => $cfg['promos']    ?? [],
        'livraison' => $cfg['livraison'] ?? [],
        'services'  => $cfg['services']  ?? [],
    ];
}
public function getHoraires(): array
{
    $cfg = $this->readAll();
    return $cfg['horaires'] ?? [];
}

public function getPromos(): array
{
    $cfg = $this->readAll();
    return $cfg['promos'] ?? [];
}

public function getLivraison(): array
{
    $cfg = $this->readAll();
    return $cfg['livraison'] ?? [];
}

public function getServices(): array
{
    $cfg = $this->readAll();
    return $cfg['services'] ?? [];
}


    public function horaires(): string
    {
        $cfg = $this->readAll();
        $rows = $cfg['horaires'] ?? [];
        if (!$rows) return "Horaires indisponibles pour le moment.";

        $txt = "🕐 *HORAIRES*\n";
        foreach ($rows as $r) {
            $l = "- {$r['day']}: ";
            if ($r['midi']) $l .= "{$r['midi']}";
            if ($r['soir']) $l .= " | {$r['soir']}";
            if ($r['note']) $l .= " ({$r['note']})";
            $txt .= $l."\n";
        }
        return $txt;
    }

    public function promos(): string
    {
        $cfg = $this->readAll();
        $rows = $cfg['promos'] ?? [];
        if (!$rows) return "Pas de promotions pour le moment.";
        $txt = "🎉 *PROMOTIONS*\n";
        foreach ($rows as $p) {
            $until = $p['until'] ? " (jusqu’au {$p['until']})" : "";
            $txt .= "- {$p['title']} : {$p['details']}{$until}\n";
        }
        return $txt;
    }

    public function services(): string
    {
        $cfg = $this->readAll();
        $rows = $cfg['services'] ?? [];
        if (!$rows) return "Services indisponibles pour le moment.";
        $txt = "📡 *SERVICES*\n";
        foreach ($rows as $s) {
            $txt .= "- {$s['service']} : {$s['description']}\n";
        }
        return $txt;
    }

    public function allergenes(): string
    {
        // Tu peux créer une feuille Allergènes plus tard; pour l’instant message générique
        return "Nous prenons les allergies au sérieux 🛡️\n".
               "- Gluten, fruits à coque, œufs/lait, poissons/fruits de mer\n".
               "Dites-moi vos allergies et je vous oriente vers les plats adaptés.";
    }

    public function livraisonZones(): string
    {
        $cfg = $this->readAll();
        $rows = $cfg['livraison'] ?? [];
        if (!$rows) return "Infos livraison indisponibles pour le moment.";
        $txt = "🚚 *ZONES & FRAIS DE LIVRAISON*\n";
        foreach ($rows as $z) {
            $txt .= "- {$z['zone']} : {$z['frais']} DH ({$z['delai']})\n";
        }
        $addr = $cfg['infos']['address'] ?? '';
        if ($addr) $txt .= "\nDépart depuis : {$addr}";
        return $txt;
    }

    public function reservation(): string
    {
        $cfg = $this->readAll();
        $phone = $cfg['infos']['phone'] ?? '';
        return "Pour réserver : indiquez *nombre de personnes*, *jour* et *heure*.\n".
               ($phone ? "Ou contactez-nous : {$phone}\n" : "");
    }

    public function infosContact(): string
    {
        $cfg = $this->readAll();
        $phone = $cfg['infos']['phone'] ?? 'n/d';
        $email = $cfg['infos']['email'] ?? 'n/d';
        $addr  = $cfg['infos']['address'] ?? 'n/d';
        return "📞 *CONTACT*\nTéléphone : {$phone}\nEmail : {$email}\nAdresse : {$addr}";
    }

    public function conseilsIntro(): string
    {
        return "Avec plaisir ! Pour vous conseiller au mieux 🎯\n".
               "- Viande, poisson ou végétarien ?\n- Plutôt épicé ou doux ?\n- Budget approximatif ?";
    }

    // --------- lecture & cache ----------
    private function readAll(): array
    {
        $mt = file_exists($this->excelPath) ? filemtime($this->excelPath) : null;
        if ($this->cache !== null && $this->mt === $mt) return $this->cache;

        if (!is_file($this->excelPath)) {
            $this->cache = []; $this->mt = null; return $this->cache;
        }
        $ss = IOFactory::load($this->excelPath);

        // Infos
        $infos = [];
        if ($sheet = $ss->getSheetByName('Infos')) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $key = trim((string)$sheet->getCell('A'.$row->getRowIndex())->getValue());
                $val = trim((string)$sheet->getCell('B'.$row->getRowIndex())->getValue());
                if ($key !== '') $infos[$key] = $val;
            }
        }

        // Horaires
        $hor = [];
        if ($sheet = $ss->getSheetByName('Horaires')) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $i = $row->getRowIndex();
                $hor[] = [
                    'day'  => trim((string)$sheet->getCell('A'.$i)->getValue()),
                    'midi' => trim((string)$sheet->getCell('B'.$i)->getValue()),
                    'soir' => trim((string)$sheet->getCell('C'.$i)->getValue()),
                    'note' => trim((string)$sheet->getCell('D'.$i)->getValue()),
                ];
            }
        }

        // Promos
        $promos = [];
        if ($sheet = $ss->getSheetByName('Promos')) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $i = $row->getRowIndex();
                $promos[] = [
                    'title'  => trim((string)$sheet->getCell('A'.$i)->getValue()),
                    'details'=> trim((string)$sheet->getCell('B'.$i)->getValue()),
                    'until'  => trim((string)$sheet->getCell('C'.$i)->getValue()),
                ];
            }
        }

        // Livraison
        $liv = [];
        if ($sheet = $ss->getSheetByName('Livraison')) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $i = $row->getRowIndex();
                $liv[] = [
                    'zone'  => trim((string)$sheet->getCell('A'.$i)->getValue()),
                    'frais' => trim((string)$sheet->getCell('B'.$i)->getValue()),
                    'delai' => trim((string)$sheet->getCell('C'.$i)->getValue()),
                ];
            }
        }

        $this->cache = [
            'infos'     => $infos,
            'horaires'  => $hor,
            'promos'    => $promos,
            'livraison' => $liv,
            'services'  => $this->readTable($ss, 'Services', ['service','description']),
        ];
        $this->mt = $mt;
        return $this->cache;
    }

    private function readTable($ss, string $sheetName, array $cols): array
    {
        $out = [];
        $sheet = $ss->getSheetByName($sheetName);
        if (!$sheet) return $out;

        foreach ($sheet->getRowIterator(2) as $row) {
            $i = $row->getRowIndex();
            $rowArr = [];
            $col = 'A';
            foreach ($cols as $idx => $key) {
                $rowArr[$key] = trim((string)$sheet->getCell(chr(ord('A')+$idx).$i)->getValue());
            }
            if (implode('', $rowArr) !== '') $out[] = $rowArr;
        }
        return $out;
    }
}
