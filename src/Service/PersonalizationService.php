<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class PersonalizationService
{
    public function __construct(private EntityManagerInterface $em) {}

    /** Récupération par ID (FIABLE) */
    public function getCatalogForItemId(int $menuItemId): array
    {
        $menuItem = $this->em->getRepository(\App\Entity\MenuItem::class)->find($menuItemId);
        if (!$menuItem) return [];

        // ⚠️ Entité renommée: MenuPersonalization
        $persos = $this->em->createQueryBuilder()
            ->select('p')
            ->from(\App\Entity\MenuPersonalization::class, 'p')
            ->where('p.menuItem = :mi')
            ->andWhere('p.actif = 1')
            ->setParameter('mi', $menuItem)
            ->orderBy('p.ordre', 'ASC')
            ->getQuery()->getResult();

        if (!$persos) return [];

        $catalog = [];
        foreach ($persos as $p) {
            /** @var \App\Entity\MenuPersonalization $p */
            $type    = strtolower((string)$p->getType()->value); // ex: 'taille','pate','supplement'
            $label   = $p->getLabel();
            $oblig   = (bool)$p->isObligatoire();
            $delta   = (float)($p->getPrixSupplement() ?? 0);
            $rawOpts = $p->getOptionsJson(); // array de valeurs lisibles

            // index des valeurs -> delta
            $values = [];
            foreach ((array)$rawOpts as $opt) {
                $opt = (string)$opt;
                if ($opt === '') continue;
                $values[$opt] = ['label'=>$opt, 'delta'=>$delta];
            }

            $input = ($type === 'supplement') ? 'multi' : 'choice';

            if ($values) {
                $catalog[] = [
                    'key'        => $type,
                    'label'      => $label ?: ucfirst($type),
                    'input'      => $input,
                    'required'   => $oblig,
                    'unit_delta' => $delta,
                    'values'     => $values,
                ];
            }
        }
        return $catalog;
    }

    /** Compat: récupération par NOM si nécessaire (utilise findOneBy nom) */
    public function getCatalogFor(string $itemName): array
    {
        $itemName = trim($itemName);
        if ($itemName === '') return [];
        $menuItem = $this->em->getRepository(\App\Entity\MenuItem::class)->findOneBy(['nom'=>$itemName]);
        return $menuItem ? $this->getCatalogForItemId((int)$menuItem->getId()) : [];
    }

    /** Additionne les deltas selon les choix (insensible à la casse/accents côté VALEUR) */
    public function computeDeltaUnit(array $customizations, array $catalog): float
    {
        $delta = 0.0;

        foreach ($catalog as $group) {
            $key    = (string)($group['key'] ?? '');
            if ($key === '') continue;

            $input  = (string)($group['input'] ?? 'choice');
            $values = (array)($group['values'] ?? []);

            if (!array_key_exists($key, $customizations)) continue;

            // Table de recherche insensible à la casse pour les labels
            $map = [];
            foreach ($values as $lab => $info) {
                $map[$this->norm($lab)] = (float)($info['delta'] ?? 0);
            }

            if ($input === 'multi') {
                $arr = is_array($customizations[$key]) ? $customizations[$key] : [$customizations[$key]];
                foreach ($arr as $v) {
                    $norm = $this->norm((string)$v);
                    if (isset($map[$norm])) $delta += $map[$norm];
                }
            } else {
                $v = is_array($customizations[$key]) ? (string)reset($customizations[$key]) : (string)$customizations[$key];
                $norm = $this->norm($v);
                if ($norm !== '' && isset($map[$norm])) $delta += $map[$norm];
            }
        }

        return $delta;
    }

    /** Vérifie que toutes les options obligatoires sont présentes (ne vérifie pas la validité des valeurs) */
    public function checkRequiredFilled(array $customizations, array $catalog): array
    {
        $missing = [];
        foreach ($catalog as $group) {
            if (!($group['required'] ?? false)) continue;
            $key   = (string)($group['key'] ?? '');
            if ($key === '') continue;
            if (!array_key_exists($key, $customizations)) {
                $missing[] = $key;
                continue;
            }
            $val = $customizations[$key];
            $empty = ($val === null)
                  || ($val === '')
                  || (is_array($val) && count($val) === 0);
            if ($empty) $missing[] = $key;
        }
        return ['ok' => count($missing) === 0, 'missing' => $missing];
    }

    public function formatCatalogFor(array $catalog): string
    {
        $lines = [];
        foreach ($catalog as $option) {
            $lines[] = sprintf(
                "%s (%s) : %s",
                $option['label'],
                $option['required'] ? 'obligatoire' : 'optionnel',
                implode(', ', array_keys((array)$option['values']))
            );
        }
        return implode("\n", $lines);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe']);
        $s = preg_replace('~[^a-z0-9 ]+~',' ',$s);
        $s = preg_replace('~\s+~',' ',$s);
        return trim($s);
    }
}
