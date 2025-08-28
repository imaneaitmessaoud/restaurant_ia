<?php
namespace App\Service;

use App\Repository\MenuPersonalizationRepository;

class PersonalizationService
{
    public function __construct(private MenuPersonalizationRepository $repo) {}

    /**
     * Retourne le catalogue de personnalisations pour un article donné (par nom).
     * Structure retournée (ex) :
     * [
     *   [
     *     'id'     => 1,
     *     'type'   => 'taille',
     *     'label'  => 'Taille',
     *     'input'  => 'select', // radio|select|checkbox|number
     *     'base'   => 0.0,      // prix supp. de base pour cette option (facultatif)
     *     'values' => [         // dictionnaire clé => ['label'=>..., 'delta'=>...]
     *       'small' => ['label'=>'Petite', 'delta'=>0],
     *       'large' => ['label'=>'Grande', 'delta'=>10]
     *     ]
     *   ],
     *   ...
     * ]
     */
    public function getCatalogFor(string $itemName): array
    {
        // On suppose que vous avez ajouté findActiveByItemName($itemName) dans le repo.
        // Sinon, adaptez pour récupérer les personnalisation via le MenuItem correspondant.
        $rows = $this->repo->findActiveByItemName($itemName);

        $out = [];
        foreach ($rows as $p) {
            // Label "humain" : on préfère le label de l'Enum s'il existe
            $label = method_exists($p->getType(), 'getLabel')
                ? $p->getType()->getLabel()
                : ucfirst($p->getType()->value);

            // Type d'input (vous l'avez déjà dans l’entité via getInputType())
            $input = $p->getInputType() ?: 'select';

            $out[] = [
                'id'     => $p->getId(),
                'type'   => $p->getType()->value,                // ex: "taille"
                'label'  => $label,                              // ex: "Taille"
                'input'  => $input,                              // select|radio|checkbox|number
                'base'   => $p->getPrixSupplementFloat(),        // ex: 0.0
                'values' => (array) $p->getOptionsJson(),        // ex: ['large'=>['label'=>'Grande','delta'=>10]]
            ];
        }

        return $out;
    }

    /**
     * Calcule le supplément total (par unité) d’un article en fonction des choix utilisateur.
     *
     * $choices est ce que vous avez parsé depuis WhatsApp (parseCustomization) :
     *   - bool(true/false) pour cases à cocher simples ("glace: oui")
     *   - int pour quantités ("fromage: 2")
     *   - string pour select/radio ("taille: grande")
     *   - array<string> pour checkbox multi ("extra: [olives, oignons]")
     *
     * On matche un choix utilisateur sur une valeur POSSIBLE soit par sa CLÉ,
     * soit par son LABEL (en normalisant accents/casse/espaces).
     */
    public function computeDelta(string $itemName, array $choices): float
    {
        $catalog = $this->getCatalogFor($itemName);
        $delta   = 0.0;

        foreach ($catalog as $opt) {
            $typeCode = $this->norm((string)($opt['type'] ?? ''));
            if ($typeCode === '' || !array_key_exists($typeCode, $choices)) {
                continue; // l’utilisateur n’a pas donné cette option
            }

            $choice = $choices[$typeCode];
            $base   = (float)($opt['base'] ?? 0);
            $input  = (string)($opt['input'] ?? 'select');
            $values = (array)($opt['values'] ?? []);

            // ------ Checkbox ------
            if ($input === 'checkbox') {
                // a) Liste de choix (ex: extras multiples)
                if (is_array($choice)) {
                    foreach ($choice as $one) {
                        $delta += $this->deltaForValue($one, $values, $base);
                    }
                    continue;
                }

                // b) Booléen (oui/non) sur un seul groupe
                if (is_bool($choice)) {
                    if ($choice === true) {
                        // Si des valeurs existent, on considère "oui" => la valeur dont label/clé est "oui" (si définie),
                        // sinon on applique le base comme supplément.
                        $applied = false;
                        foreach ($values as $k => $info) {
                            $kNorm = $this->norm((string)$k);
                            $lNorm = $this->norm((string)($info['label'] ?? $k));
                            if ($kNorm === 'oui' || $lNorm === 'oui') {
                                $delta += (float)($info['delta'] ?? $base);
                                $applied = true;
                                break;
                            }
                        }
                        if (!$applied) {
                            $delta += $base;
                        }
                    }
                    continue;
                }

                // c) Texte simple (rare pour checkbox) => on tente un match direct
                if (is_string($choice)) {
                    $delta += $this->deltaForValue($choice, $values, $base);
                    continue;
                }
            }

            // ------ Radio / Select ------
            if ($input === 'radio' || $input === 'select') {
                if (is_string($choice)) {
                    $delta += $this->deltaForValue($choice, $values, $base);
                }
                continue;
            }

            // ------ Number (quantité multiplicative) ------
            if ($input === 'number') {
                if (is_numeric($choice)) {
                    // convention : le 'base' est le supplément unitaire multiplié par la quantité
                    $delta += $base * (int)$choice;
                }
                continue;
            }

            // ------ Fallback : bool TRUE applique base ------
            if ($choice === true) {
                $delta += $base;
            }
        }

        return $delta;
    }

    /**
     * Retourne le delta pour une valeur choisie (string) en la faisant
     * correspondre soit à la CLÉ, soit au LABEL d’une entrée du dictionnaire $values.
     *
     * @param string $userValue  ex: "grande"
     * @param array  $values     ex: ['large'=>['label'=>'Grande','delta'=>10]]
     * @param float  $fallback   delta à appliquer si aucune valeur précise n’est trouvée
     */
    private function deltaForValue(string $userValue, array $values, float $fallback): float
    {
        $want = $this->norm($userValue);

        foreach ($values as $key => $info) {
            $kNorm = $this->norm((string)$key);
            $lNorm = $this->norm((string)($info['label'] ?? $key));
            if ($want === $kNorm || $want === $lNorm) {
                return (float)($info['delta'] ?? $fallback);
            }
        }

        // aucune correspondance exacte => applique le base par défaut
        return $fallback;
    }

    /** Normalisation "douce" : lower, remove accents, remplacer non-alnum par _ */
    private function norm(string $s): string
    {
        $s = mb_strtolower($s);
        // Remplacement manuel (compatible Windows sans dépendre d'iconv)
        $s = strtr($s, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ì'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ò'=>'o','ó'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n'
        ]);
        $s = preg_replace('~[^a-z0-9]+~', '_', $s);
        return trim($s, '_');
    }
}
