<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class PersonalizationService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Construit le catalogue d’options pour un article par son NOM (champ 'nom' en BDD).
     * Renvoie un array prêt à afficher / calculer.
     */
    public function getCatalogFor(string $itemName): array
    {
        $itemName = trim($itemName);
        if ($itemName === '') return [];

        // ⚠️ Ici on cherche par champ 'nom' (adapter si c'est 'name')
        $menuItem = $this->em->getRepository(\App\Entity\MenuItem::class)
            ->findOneBy(['nom' => $itemName]);

        if (!$menuItem) return [];

        // Récupérer suppléments actifs
        $supps = $this->em->createQueryBuilder()
            ->select('s')
            ->from(\App\Entity\MenuItemSupplement::class, 's')
            ->where('s.menuItem = :mi')
            ->andWhere('s.actif = 1')
            ->setParameter('mi', $menuItem)
            ->orderBy('s.ordre', 'ASC')
            ->getQuery()->getResult();

        if (!$supps) return [];

        $catalog = [];
        foreach ($supps as $s) {
            /** @var \App\Entity\MenuItemSupplement $s */
            $type  = (string)$s->getType();
            $oblig = (bool)$s->isObligatoire();
            $delta = (float)$s->getPrixSupplement();

            // options_json peut être déjà un array OU une string JSON
            $raw = $s->getOptionsJson();
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $options = $decoded;
                } else {
                    // string simple "A,B,C" → on découpe
                    $options = array_map('trim', preg_split('~\s*,\s*~', $raw));
                }
            } elseif (is_array($raw)) {
                $options = $raw;
            } else {
                $options = [];
            }

            $input = ($type === 'supplement') ? 'multi' : 'choice';

            $values = [];
            foreach ($options as $opt) {
                $label = (string)$opt;
                if ($label === '') continue;
                $values[$label] = ['label' => $label, 'delta' => $delta];
            }

            if (!$values) continue;

            $catalog[] = [
                'key'        => $type,
                'label'      => $s->getLabel() ?? ucfirst($type), // Utilisez l'enum si disponible
                'input'      => $input,
                'required'   => $oblig,
                'unit_delta' => $delta,
                'values'     => $values,
            ];
        }

        return $catalog;
    }

    /**
     * Additionne les deltas selon les choix client.
     * $customizations:
     *   - 'taille' => 'Grande'
     *   - 'pate' => 'Fine'
     *   - 'supplement' => ['Mozzarella','Parmesan']
     */
    public function computeDeltaUnit(array $customizations, array $catalog): float
    {
        $delta = 0.0;

        foreach ($catalog as $group) {
            $key    = $group['key'] ?? null;
            if (!$key) continue;

            $input  = $group['input'] ?? 'choice';
            $values = (array)($group['values'] ?? []);

            if (!array_key_exists($key, $customizations)) continue;

            $choice = $customizations[$key];

            if ($input === 'multi') {
                $arr = is_array($choice) ? $choice : [$choice];
                foreach ($arr as $val) {
                    $v = (string)$val;
                    if (isset($values[$v])) $delta += (float)$values[$v]['delta'];
                }
            } else {
                $v = is_array($choice) ? (string)reset($choice) : (string)$choice;
                if ($v !== '' && isset($values[$v])) $delta += (float)$values[$v]['delta'];
            }
        }

        return $delta;
    }

    /** Vérifie que toutes les options obligatoires sont présentes. */
    public function checkRequiredFilled(array $customizations, array $catalog): array
    {
        $missing = [];
        foreach ($catalog as $group) {
            if (!($group['required'] ?? false)) continue;
            $key   = $group['key'] ?? null;
            $input = $group['input'] ?? 'choice';
            if (!$key) continue;
            if (!array_key_exists($key, $customizations)) {
                $missing[] = $key;
                continue;
            }
            $val = $customizations[$key];
            if ($input === 'choice') {
                $empty = ($val === null)
                      || ($val === '')
                      || (is_array($val) && count($val) === 0);
                if ($empty) $missing[] = $key;
            } else {
                // multi : on tolère vide si non obligatoire (mais ici obligatoire)
                if (!is_array($val) || count($val) === 0) $missing[] = $key;
            }
        }
        return ['ok' => count($missing) === 0, 'missing' => $missing];
    }

    private function formatCatalogFor(array $catalog): string
    {
        $lines = [];
        foreach ($catalog as $option) {
            $lines[] = sprintf("%s (%s) : %s",
                $option['label'],
                $option['required'] ? 'obligatoire' : 'optionnel',
                implode(', ', array_keys($option['values']))
            );
        }
        return implode("\n", $lines);
    }
}
