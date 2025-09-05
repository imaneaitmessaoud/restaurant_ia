<?php
namespace App\Service;

use Doctrine\Persistence\ManagerRegistry;

class MenuProvider
{
    public function __construct(private ManagerRegistry $doctrine) {}

    /**
     * Retourne le menu *depuis la BDD* :
     * - uniquement les articles disponibles
     * - groupés par catégorie (on renvoie category_name)
     * - avec les clés normalisées: name, price, category_name, disponible, id
     */
    public function getMenu(): array
    {
        $conn = $this->doctrine->getConnection();

        $sql = <<<SQL
SELECT
  mi.id,
  mi.nom,
  mi.prix,
  mi.disponible,
  mi.ordre,
  c.nom  AS category_name,
  c.ordre AS cat_ordre
FROM menu_items mi
LEFT JOIN menu_categories c ON c.id = mi.category_id
WHERE mi.disponible = 1
ORDER BY
  (c.ordre IS NULL), c.ordre,
  (mi.ordre IS NULL), mi.ordre,
  mi.nom
SQL;

        $rows = $conn->fetchAllAssociative($sql);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'            => (int)$r['id'],
                'name'          => (string)$r['nom'],
                'price'         => (float)str_replace(',', '.', (string)$r['prix']),
                'disponible'    => (bool)$r['disponible'],
                'category_name' => $r['category_name'] ?: 'Autres',
            ];
        }
        return $out;
    }
}
