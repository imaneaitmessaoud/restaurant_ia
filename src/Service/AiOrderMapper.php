<?php
namespace App\Service;

use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\User;
use App\Entity\MenuItem;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use Doctrine\ORM\EntityManagerInterface;

class AiOrderMapper
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function buildCommandeFromPayload(array $p): Commande
    {
        $order      = $p['order'] ?? [];
        $items      = $order['items'] ?? [];
        $service    = strtoupper((string)($order['service_type'] ?? 'SUR_PLACE'));
        $addr       = $order['delivery_address'] ?? null;
        $customer   = $order['customer'] ?? [];
        $name       = trim((string)($customer['name']  ?? 'WhatsApp Client'));
        $rawPhone   = preg_replace('~\D+~', '', (string)($customer['phone'] ?? ''));

        // -------- User : find or create (email UNIQUE garanti)
        $user = null;
        if ($rawPhone !== '') {
            $user = $this->em->getRepository(User::class)->findOneBy(['telephone' => $rawPhone]);
        }
        if (!$user) {
            $uniqueSuffix = bin2hex(random_bytes(4)); // 8 hexa
            $safePhone    = $rawPhone !== '' ? $rawPhone : ('0'.random_int(100000000, 999999999));

            $user = new User();
            $user->setNom($name);
            $user->setTelephone($safePhone);
            $user->setEmail(($rawPhone !== '' ? $rawPhone : 'guest+'.$uniqueSuffix).'@auto.local');
            $user->setPassword(password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT));
            $this->em->persist($user);
        }

        // -------- Commande
        $cmd = new Commande();
        $cmd->setUser($user);
        $cmd->setStatut(StatutCommandeEnum::CONFIRMEE);

        $type = match ($service) {
            'SUR_PLACE' => TypeServiceEnum::SUR_PLACE,
            'EMPORTER'  => TypeServiceEnum::EMPORTER,
            'LIVRAISON' => TypeServiceEnum::LIVRAISON,
            default     => TypeServiceEnum::SUR_PLACE,
        };
        $cmd->setTypeService($type);

        if ($type === TypeServiceEnum::LIVRAISON) {
            $cmd->setAdresseLivraison($addr ?: null);
        } else {
            $cmd->setAdresseLivraison(null);
        }

        // -------- Lignes
        $grand = 0.0;
        $menuRepo = $this->em->getRepository(MenuItem::class);

        foreach ($items as $it) {
            $nameItem   = trim((string)($it['name'] ?? 'Article'));
            $qty        = max(1, (int)($it['quantity'] ?? $it['qty'] ?? 1));

            $base       = (float)($it['base_unit_price']  ?? 0);
            $delta      = (float)($it['delta_unit_price'] ?? 0);
            $final      = $it['final_unit_price'] ?? null;
            $unit       = is_numeric($final) ? (float)$final : ($base + $delta);
            if ($unit < 0) $unit = 0;

            $menuItem = null;
            if ($nameItem !== '') {
                $menuItem = $menuRepo->findOneBy(['nom' => $nameItem]);
            }

            $line = new CommandeItem();
            $line->setCommande($cmd);
            $line->setQuantite($qty);
            $line->setPrixUnitaireFloat($unit);
            $line->setPersonalisationJson($it['customizations'] ?? $it['custom'] ?? []);
            $line->setCommentaire($nameItem);
            if ($menuItem) {
                $line->setMenuItem($menuItem); // nullable=true côté mapping
            }

            $cmd->addCommandeItem($line);
            $this->em->persist($line);

            $grand += $unit * $qty;
        }

        $cmd->setTotal(number_format($grand, 2, '.', ''));

        $snapshot = [
            'items'        => $items,
            'type_service' => $service,
            'address'      => $addr,
            'name'         => $name,
            'phone'        => $rawPhone,
            'total_calc'   => $grand,
            'created_at'   => date('c'),
        ];
        $cmd->setCommentaire(json_encode($snapshot, JSON_UNESCAPED_UNICODE));

        return $cmd;
    }
}
