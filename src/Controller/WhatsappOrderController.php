<?php
namespace App\Controller;

use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\User;
use App\Entity\MenuItem;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use App\Service\MenuProvider;
use App\Service\ConversationStore;
use App\Service\RecommendationService;
use App\Service\InfoProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PersonalizationService;

class WhatsappOrderController extends AbstractController
{
    public function __construct(
    private MenuProvider $menu,
    private ConversationStore $store,
    private RecommendationService $reco,
    private EntityManagerInterface $em,
    private InfoProvider $info,
    private PersonalizationService $perso // ⬅️ AJOUT
) {}


    #[Route('/webhook/whatsapp', name: 'whatsapp_order', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        // --- Vérif Twilio (désactivée si TWILIO_VERIFY=false) ---
        $verify    = ($_ENV['TWILIO_VERIFY'] ?? 'false') === 'true';
        $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        if ($verify) {
            if (!$authToken) return $this->twiml('Server misconfigured', 500);
            if (!$this->isTwilioRequest($request, $authToken)) return $this->twiml('Unauthorized', 403);
        }

        $from  = (string) $request->request->get('From', 'unknown'); // "whatsapp:+2126..."
        $body  = trim((string)$request->request->get('Body', ''));
        $lower = mb_strtolower($body);

        // État de conv
        $state = $this->store->get($from);

        // Idempotence (optionnelle) si ConversationStore propose seen/markSeen
        $sid = (string) $request->request->get('MessageSid', '');
        if ($sid && method_exists($this->store,'seen') && method_exists($this->store,'markSeen')) {
            if ($this->store->seen($sid)) return $this->twiml("OK");
            $this->store->markSeen($sid);
        }

        // Préfixe si le fichier Menu Excel a changé
        $prefix = $this->maybeMenuUpdatedPrefix($state, $this->menu, $from);

        // --- Salutations naturelles ---
        if (preg_match('~^(salut|salam|bonjour|bonsoir|hey|coucou)\b~i', $lower)) {
            $intro  = $this->info->intro();
            $prompt = "Souhaitez-vous voir *le menu*, connaître *les horaires*, *les promos*, ou *commencer une commande* ?";
            return $this->twiml($prefix.$intro."\n\n".$prompt);
        }
        // --- Type de service ---
        if (($state['step'] ?? null) === 'await_service') {
            if (preg_match('~sur\s*place~i', $lower)) {
                $state['type_service'] = 'sur_place';
            } elseif (preg_match('~emporter|à emporter|a emporter~i', $lower)) {
                $state['type_service'] = 'emporter';
            } elseif (preg_match('~livrai?s?on|livra?son|livrer~i', $lower)) {
                $state['type_service'] = 'livraison';
            } else {
                return $this->twiml("Merci de préciser : *sur place*, *emporter* ou *livraison*.");
            }

            if ($state['type_service'] === 'livraison') {
                $state['step'] = 'await_address';
                $this->store->set($from, $state);
                return $this->twiml("📍 Donnez l’adresse complète SVP.");
            }

            $state['address'] = null;
            $state['step']    = 'await_confirm';
            $this->store->set($from, $state);
            return $this->twiml($prefix.$this->recap($state)."\n\nPour valider : *valider Nom, Téléphone*");
        }

        // --- FAQs (Excel via InfoProvider) ---
        if (preg_match('~\b(horaire|ouvert|fermé|ouverture)\b~i', $lower))      return $this->twiml($prefix.$this->info->horaires());
        if (preg_match('~\b(promo|promotion|réduction|offre|happy hour)\b~i', $lower)) return $this->twiml($prefix.$this->info->promos());
        if (preg_match('~\b(wifi|wi[- ]?fi|service|paiement|clim|prise|ambiance)\b~i', $lower)) return $this->twiml($prefix.$this->info->services());
        if (preg_match('~\b(allerg|gluten|lactose|végan|végéta)\b~i', $lower))  return $this->twiml($prefix.$this->info->allergenes());
        // On parle explicitement des ZONES / FRAIS / DÉLAIS de livraison
if (preg_match('~\b(livraison|livrer)\b~i', $lower) 
    && preg_match('~\b(zones?|frais|d[ée]lais?|tarifs?|prix)\b~i', $lower)) {
    return $this->twiml($prefix.$this->info->livraisonZones());
}

        if (preg_match('~\b(conseil|recomman|id[ée]e)\b~i', $lower))            return $this->twiml($prefix.$this->info->conseilsIntro());
        if (preg_match('~\b(r[ée]serv|table|anniversaire)\b~i', $lower))        return $this->twiml($prefix.$this->info->reservation());
        if (preg_match('~\b(contact|t[ée]l[ée]phone|email|mail)\b~i', $lower))  return $this->twiml($prefix.$this->info->infosContact());

        // --- Suivi commande "CMD-000123" ---
        if (preg_match('~(où|ou|statut|suivi).*(commande|cmd)[^\d]*(\d{6})~i', $lower, $m)) {
            $id = (int)$m[3];
            $cmd = $this->em->getRepository(Commande::class)->find($id);
            if (!$cmd) return $this->twiml("Je ne trouve pas la commande *CMD-".sprintf('%06d',$id)."*.");
            return $this->twiml(
                "📋 Statut de *CMD-".sprintf('%06d',$cmd->getId())."* : ".$cmd->getStatut()->getLabel().
                "\nTotal : ".$cmd->getFormattedTotal()
            );
        }

        // --- Commandes rapides ---
        if (preg_match('~^annuler\b~i', $body)) {
            $this->store->reset($from);
            return $this->twiml("🗑️ Commande annulée. Tapez *menu* pour recommencer.");
        }
        if (preg_match('~^modifier\b~i', $body)) {
            $state['step']  = 'await_items';
            $state['items'] = [];
            $state['total'] = 0.0;
            $this->store->set($from, $state);
            return $this->twiml("D’accord. Dites-moi ce que vous souhaitez (ex: *Margherita x2, Coca x1*).");
        }

        // --- Afficher le menu (phrases naturelles incluses) ---
        if (preg_match('~\b(menu|voir le menu|donne[rz]? le menu|peux[- ]?tu.*menu|je veux.*menu)\b~i', $lower)) {
            $items = $this->menu->getMenu();
            if (!$items) return $this->twiml("Désolé, le menu est indisponible pour le moment.");
            $txt = "📋 Voici notre menu :\n\n";
            foreach ($items as $i) $txt .= "🍽 {$i['name']} — {$i['price']} MAD\n";
            $state['step'] = 'await_items';
            $state['items'] = $state['items'] ?? [];
            $state['total'] = $state['total'] ?? 0.0;
            $this->store->set($from, $state);
            return $this->twiml($prefix.$txt."\n\nPour commander : *NomDuPlat xQuantité* (ex: *Margherita x2*).");
        }

        // --- Ajustements panier (retirer / quantité) ---
        if (preg_match('~^retirer\s+(.+)$~i', $body, $m)) {
            $want = trim($m[1]); $qty = 1;
            if (preg_match('~(.+)\s+x\s*(\d+)$~i', $want, $mm)) { $want = trim($mm[1]); $qty = (int)$mm[2]; }
            $new = [];
            foreach ($state['items'] ?? [] as $it) {
                if (mb_strtolower($it['name']) === mb_strtolower($want)) {
                    $it['qty'] -= $qty;
                    if ($it['qty'] > 0) $new[] = $it;
                } else $new[] = $it;
            }
            $state['items'] = $new;
            $state['total'] = array_reduce($new, fn($s,$i)=>$s+$i['qty']*$i['price'], 0.0);
            $this->store->set($from, $state);
            return $this->twiml($prefix.$this->recap($state)."\n\nTapez *valider Nom, Téléphone* quand c'est bon.");
        }

        if (preg_match('~^quantit[eé]\s+(.+?)\s+x\s*(\d+)$~i', $body, $m)) {
            $name = trim($m[1]); $q = max(1,(int)$m[2]);
            foreach ($state['items'] ?? [] as &$it) {
                if (mb_strtolower($it['name']) === mb_strtolower($name)) $it['qty'] = $q;
            } unset($it);
            $state['total'] = array_reduce($state['items'] ?? [], fn($s,$i)=>$s+$i['qty']*$i['price'], 0.0);
            $this->store->set($from, $state);
            return $this->twiml($prefix.$this->recap($state)."\n\nTapez *valider Nom, Téléphone* quand c'est bon.");
        }

        // --- Démarrage / ajout d'articles ---
        if (($state['step'] ?? null) === 'await_items' || preg_match('~^\s*(commander|je veux|je prends)\b~i', $body)) {
            $picked = $this->parseItems($body);
            if (!$picked && ($state['step'] ?? null) !== 'await_items') {
                $state['step'] = 'await_items';
                $this->store->set($from, $state);
                return $this->twiml("Dites ce que vous voulez : *Plat xQte* (ex: *Margherita x2*).");
            }

            foreach ($picked as $p) {
                $row = $this->menu->findByName($p['name']);
                if (!$row) continue;
                $state['items'][] = [
                    'name'  => $row['name'],
                    'qty'   => max(1,(int)$p['qty']),
                    'price' => (float)$row['price'],
                ];
            }
            // ✅ Vérifie s'il existe des personnalisations pour le **dernier article ajouté**
$idxLast = array_key_last($state['items'] ?? []);
if ($idxLast !== null) {
    $lastName = $state['items'][$idxLast]['name'];
    $catalog  = $this->perso->getCatalogFor($lastName);
    if (!empty($catalog)) {
        // On passe en étape de personnalisation
        $state['step'] = 'await_customization';
        $state['customizing'] = [
            'index'    => $idxLast,
            'itemName' => $lastName,
            'catalog'  => $catalog, // on garde le catalogue pour formater l'invite
        ];
        $this->store->set($from, $state);

        $msg = "🛠️ Personnalisation pour *{$lastName}* :\n";
        $msg .= $this->formatCatalogFor($catalog);
        $msg .= "\n\nRépondez comme ceci, par exemple :\n";
        $msg .= "- taille: large; cuisson: saignante\n";
        $msg .= "- fromage: 2; glace: oui\n";
        return $this->twiml($prefix.$msg);
    }
}

            $state['total'] = array_reduce($state['items'] ?? [], fn($s,$i)=>$s+$i['qty']*$i['price'], 0.0);
            $state['step']  = 'await_service';
            $this->store->set($from, $state);
            return $this->twiml($prefix.$this->recap($state)."\n\nChoisissez *sur place*, *emporter* ou *livraison*.");
        }

        

        // --- Adresse livraison ---
        if (($state['step'] ?? null) === 'await_address') {
            if (mb_strlen($body) < 5) return $this->twiml("Adresse trop courte, pouvez-vous préciser ?");
            $state['address'] = $body;
            $state['step']    = 'await_confirm';
            $this->store->set($from, $state);
            return $this->twiml($prefix.$this->recap($state)."\n\nPour valider : *valider Nom, Téléphone*");
        }
// --- Personnalisation en cours ---
if (($state['step'] ?? null) === 'await_customization') {
    $conf = $this->parseCustomization($body); // ex: ["taille"=>"large","cuisson"=>"saignante","fromage"=>2,"glace"=>true]

    $idx  = (int)($state['customizing']['index'] ?? -1);
    $name = (string)($state['customizing']['itemName'] ?? '');

    if ($idx < 0 || !isset($state['items'][$idx]) || !$name) {
        // Sécurité: si l'état est bancal, on reprend sur le service
        $state['step'] = 'await_service';
        $this->store->set($from, $state);
        return $this->twiml($this->recap($state)."\n\nChoisissez *sur place*, *emporter* ou *livraison*.");
    }

    // ✅ Calcule le delta prix des options sélectionnées
    $delta = $this->perso->computeDelta($name, $conf);

    // On sauvegarde la sélection et le delta dans l'item
    $state['items'][$idx]['custom'] = $conf;
    $state['items'][$idx]['delta']  = $delta; // delta **par unité**

    // Recalcule le total (incluant delta)
    $state['total'] = array_reduce($state['items'] ?? [], function($s, $i) {
        $u = (float)$i['price'] + (float)($i['delta'] ?? 0);
        return $s + $u * (int)$i['qty'];
    }, 0.0);

    // On repart sur le choix du type de service
    $state['step'] = 'await_service';
    unset($state['customizing']);
    $this->store->set($from, $state);

    $ok = "✅ Personnalisation appliquée à *{$name}*.";
    return $this->twiml($prefix.$ok."\n\n".$this->recap($state)."\n\nChoisissez *sur place*, *emporter* ou *livraison*.");
}

        // --- Validation ---
        if (($state['step'] ?? null) === 'await_confirm') {
            if (!preg_match('~^valider\s+([^,]+)\s*,\s*([\d\s\+]+)~i', $body, $m)) {
                return $this->twiml("Format attendu : *valider Nom, Téléphone*");
            }
            $state['name']  = trim($m[1]);
            $state['phone'] = preg_replace('~\D+~','',$m[2] ?? '');

            // User
            $user = $this->findOrCreateUser($state['name'], $state['phone']);

            // Commande
            $commande = new Commande();
            $commande->setUser($user);
            $commande->setStatut(StatutCommandeEnum::CONFIRMEE);
            $commande->setTypeService(match ($state['type_service'] ?? 'sur_place') {
                'sur_place' => TypeServiceEnum::SUR_PLACE,
                'emporter'  => TypeServiceEnum::EMPORTER,
                'livraison' => TypeServiceEnum::LIVRAISON,
                default     => TypeServiceEnum::SUR_PLACE
            });
            $commande->setAdresseLivraison(($state['type_service'] ?? null) === 'livraison' ? ($state['address'] ?? null) : null);

            $this->em->persist($commande);

            // Lignes (dédup par nom) — on lie MenuItem si trouvé
            $linesTotal = 0.0;
            $grouped = [];
            foreach (($state['items'] ?? []) as $it) {
                $unit = (float)$it['price'] + (float)($it['delta'] ?? 0); // prix Excel + delta options
$k = mb_strtolower(trim($it['name'])) . '|' . md5(json_encode($it['custom'] ?? [])); // évite de fusionner 2 configs différentes
if (!isset($grouped[$k])) {
    $grouped[$k] = [
        'name'   => $it['name'],
        'qty'    => (int)$it['qty'],
        'price'  => $unit,
        'custom' => $it['custom'] ?? []
    ];
} else {
    $grouped[$k]['qty'] += (int)$it['qty'];
}

            }

            foreach ($grouped as $g) {
               $line = new CommandeItem();
$line->setCommande($commande);
$line->setQuantite($g['qty']);
$line->setPrixUnitaire(number_format($g['price'], 2, '.', '')); // déjà base + delta
$line->setPersonalisationJson($g['custom'] ?: null);            // ⬅️ on stocke la personnalisation
$line->setCommentaire($g['name']);

                $menuItem = $this->em->getRepository(MenuItem::class)->findOneBy(['nom' => $g['name']]);
                if ($menuItem) {
                    $line->setMenuItem($menuItem); // OK si JoinColumn nullable=true; sinon il FAUT un MenuItem
                }

                $commande->addCommandeItem($line);
                $this->em->persist($line);

                $linesTotal += $g['price'] * $g['qty'];
            }

            $commande->setTotal(number_format($linesTotal, 2, '.', ''));

            // Snapshot JSON
            $snapshot = [
                'items'        => $state['items'] ?? [],
                'type_service' => $state['type_service'] ?? null,
                'address'      => $state['address'] ?? null,
                'name'         => $state['name'] ?? null,
                'phone'        => $state['phone'] ?? null,
                'total_calc'   => $linesTotal,
                'created_at'   => date('c'),
            ];
            $commande->setCommentaire(json_encode($snapshot, JSON_UNESCAPED_UNICODE));

            // Sauvegarde
            $this->em->flush();
            $this->em->refresh($commande);

            // Réponse finale
            $ref       = $commande->getReference();
            $typeLabel = $commande->getTypeService()->getLabel();

            $msg  = "🎉 Commande validée !\n";
            $msg .= "- Ref: {$ref}\n";
            $msg .= "- Client: {$state['name']}\n";
            $msg .= "- Type: {$typeLabel}\n";
            if ($commande->isDelivery() && $commande->getAdresseLivraison()) {
                $msg .= "- Adresse: {$commande->getAdresseLivraison()}\n";
            }
            $msg .= "- Total: ".number_format($linesTotal, 2)." DH\n";
            $msg .= "Merci 🙏";

            $this->store->reset($from);
            return $this->twiml($msg);
        }

        // --- Reco (inactif > 30j)
        $digits = preg_replace('~\D+~', '', $from) ?: '0000';
        $user = $this->findUserByPhone($digits);
        if ($user && $this->reco->isInactiveSince($user, 30)) {
            $tops = $this->reco->topItems($user, 3);
            if ($tops) {
                $txt = "👋 Heureux de vous revoir ! Suggestions basées sur vos précédentes commandes :\n";
                foreach ($tops as $t) $txt .= "• {$t}\n";
                $txt .= "\nTapez *menu* pour voir la carte ou *commander <plat> x<qte>* pour commencer.";
                return $this->twiml($txt);
            }
        }

        // --- Fallback
        return $this->twiml("Je peux vous aider à *voir le menu* (tapez *menu*) et *passer commande*.\nEx: *Margherita x2*.");
    }

    // ------------------ Helpers ------------------

    private function parseItems(string $text): array
    {
        $text = preg_replace('~^(commander|je veux|je prends)\s+~i', '', trim($text));
        $parts = preg_split('~[,;]| et ~i', $text);

        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;

            if (preg_match('~^\s*(.+?)\s*[xX]\s*(\d+)\s*$~u', $p, $m)) {
                $out[] = ['name' => trim($m[1]), 'qty' => max(1, (int)$m[2])];
            } else {
                $out[] = ['name' => $p, 'qty' => 1];
            }
        }
        return $out;
    }

    private function recap(array $state): string
    {
        $lines = ["✅ Récap commande :"];
        foreach (($state['items'] ?? []) as $it) {
            $lines[] = "- {$it['name']} x{$it['qty']}";
        }
        $total = array_reduce($state['items'] ?? [], function($s, $i){
    $unit = (float)$i['price'] + (float)($i['delta'] ?? 0); // ⬅️ inclut delta
    return $s + $unit * (int)$i['qty'];
}, 0.0);

        $lines[] = "- Total : ".number_format($total, 2)." MAD";
        if (($state['type_service'] ?? null) === 'livraison' && !empty($state['address'])) {
            $lines[] = "- Adresse: ".$state['address'];
        }
        return implode("\n", $lines);
    }

    private function findUserByPhone(string $digits): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['telephone' => $digits]);
    }

    private function findOrCreateUser(string $name, string $digits): User
    {
        if ($u = $this->findUserByPhone($digits)) return $u;

        $u = new User();
        $u->setNom($name);
        $u->setTelephone($digits);
        $u->setEmail($digits.'@auto.local');
        $u->setPassword(password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT));

        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function twiml(string $message, int $status = 200): Response
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
               "<Response>\n".
               "    <Message>".htmlspecialchars($message, ENT_XML1)."</Message>\n".
               "</Response>";
        return new Response($xml, $status, ['Content-Type' => 'application/xml']);
    }

    private function maybeMenuUpdatedPrefix(array &$state, MenuProvider $menu, string $from): string
    {
        if (!method_exists($menu, 'getChecksum')) return '';
        $checksum = $menu->getChecksum();
        if ($checksum && (($state['menu_checksum'] ?? null) !== $checksum)) {
            $state['menu_checksum'] = $checksum;
            $this->store->set($from, $state);
            return "ℹ️ Le menu vient d’être mis à jour.\n\n";
        }
        return '';
    }

    private function isTwilioRequest(Request $request, string $authToken): bool
    {
        $signature = $request->headers->get('X-Twilio-Signature', '');
        $url       = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $params    = $request->request->all();
        ksort($params);

        $data = $url;
        foreach ($params as $k => $v) $data .= $k . $v;

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return $signature && hash_equals($expected, $signature);
    }

    /** Affiche joliment un catalogue d’options pour un article */
private function formatCatalogFor(array $catalog): string
{
    // $catalog = [
    //   ['type'=>'taille','label'=>'Taille','input'=>'select','base'=>0,'values'=>['small'=>['label'=>'Petite','delta'=>0], ...]],
    //   ...
    // ]
    $out = [];
    foreach ($catalog as $opt) {
        $line = "• *{$opt['label']}* ({$opt['input']})";
        $vals = [];
        foreach (($opt['values'] ?? []) as $key => $info) {
            $lab = $info['label'] ?? $key;
            $d   = (float)($info['delta'] ?? 0);
            $vals[] = $d > 0 ? "{$lab} (+{$d} DH)" : $lab;
        }
        if ($vals) $line .= " : " . implode(', ', $vals);
        $out[] = $line;
    }
    return implode("\n", $out);
}

/** Parse une réponse utilisateur en dictionnaire de choix par type (très tolérant) */
private function parseCustomization(string $text): array
{
    // ex entrées possibles :
    // "taille: large; cuisson: saignante; fromage: 2; glace: oui"
    $pairs = preg_split('~[;\n]+~', $text);
    $out = [];
    foreach ($pairs as $p) {
        if (!str_contains($p, ':')) continue;
        [$k, $v] = array_map('trim', explode(':', $p, 2));
        $k = $this->normKey($k);
        $v = trim($v);

        // normalisations rapides
        if ($v === '' || preg_match('~^(non|no|0)$~i', $v))  { $out[$k] = false; continue; }
        if (preg_match('~^(oui|yes|1)$~i', $v))              { $out[$k] = true;  continue; }
        if (preg_match('~^\d+$~', $v))                      { $out[$k] = (int)$v; continue; }

        // valeur textuelle (clé d’option)
        $out[$k] = mb_strtolower($v);
    }
    return $out;
}

private function normKey(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
    $s = preg_replace('~[^a-z0-9]+~', '_', $s);
    return trim($s, '_');
}

}
