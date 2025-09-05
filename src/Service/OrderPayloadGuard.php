<?php
namespace App\Service;

/**
 * Valide minimalement le payload IA avant persistance.
 * Retourne un tableau d'erreurs (vide = OK).
 */
class OrderPayloadGuard
{
    public function validate(array $p): array
    {
        $err = [];
        $order = $p['order'] ?? [];
        $actions = $p['actions'] ?? [];

        // items
        $items = $order['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $err[] = "aucun article dans la commande";
        }

        // service_type
        $st = strtoupper((string)($order['service_type'] ?? ''));
        if (!in_array($st, ['SUR_PLACE','EMPORTER','LIVRAISON'], true)) {
            $err[] = "type de service invalide (SUR_PLACE, EMPORTER ou LIVRAISON)";
        }

        // adresse requise si LIVRAISON
        if ($st === 'LIVRAISON') {
            $addr = trim((string)($order['delivery_address'] ?? ''));
            if ($addr === '') {
                $err[] = "adresse de livraison manquante";
            }
        }

        // confirmation
        $confirmed = (bool)($actions['confirmed'] ?? false);
        if (!$confirmed) {
            $err[] = "confirmation client manquante";
        }

        // prix/quantités (soft check)
        foreach ($items as $i) {
            $q = (int)($i['quantity'] ?? $i['qty'] ?? 0);
            if ($q < 1) $err[] = "quantité invalide pour ".$this->safeName($i);

            $hasFinal = is_numeric($i['final_unit_price'] ?? null);
            $hasBaseDelta = is_numeric($i['base_unit_price'] ?? null) || is_numeric($i['delta_unit_price'] ?? null);
            if (!$hasFinal && !$hasBaseDelta) {
                $err[] = "prix unitaire manquant pour ".$this->safeName($i);
            }
        }

        return $err;
    }

    private function safeName(array $i): string
    {
        $n = trim((string)($i['name'] ?? 'article'));
        return $n === '' ? 'article' : $n;
    }
}
