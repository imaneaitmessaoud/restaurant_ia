<?php
namespace App\Controller;

use App\Service\MenuProvider;
use App\Service\ConversationStore;
use App\Service\InfoProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PersonalizationService;

// IA (Groq)
use App\Service\GroqOrderAgent;
use App\Service\OrderPayloadGuard;
use App\Service\AiOrderMapper;

use Psr\Log\LoggerInterface;

class WhatsappOrderController extends AbstractController
{
    public function __construct(
        private MenuProvider $menu,
        private ConversationStore $store,
        private EntityManagerInterface $em,
        private InfoProvider $info,
        private PersonalizationService $perso,
        private GroqOrderAgent $groq,
        private OrderPayloadGuard $guard,
        private AiOrderMapper $mapper
    ) {}

    #[Route('/webhook/whatsapp', name: 'whatsapp_order', methods: ['POST'])]
    public function webhook(Request $request, LoggerInterface $logger): Response
    {
        // --- Vérif Twilio (optionnelle)
        $verify    = ($_ENV['TWILIO_VERIFY'] ?? 'false') === 'true';
        $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        if ($verify) {
            if (!$authToken) return $this->twiml('Server misconfigured', 500);
            if (!$this->isTwilioRequest($request, $authToken)) return $this->twiml('Unauthorized', 403);
        }

        $from  = (string)$request->request->get('From', 'unknown'); // ex: "whatsapp:+2126..."
        $body  = trim((string)$request->request->get('Body', ''));
        $lower = mb_strtolower($body);

        $logger->info('[WHATSAPP] hit', ['from'=>$from,'body'=>$body,'t'=>date('c')]);

        // Rien à traiter (ni texte ni média)
        $numMedia = (int)$request->request->get('NumMedia', 0);
        if ($body === '' && $numMedia === 0) {
            return $this->twiml($this->brandHeader()."Écris par ex : *Pizza Margherita x2* ou *menu*.");
        }

        // Idempotence douce
        $sid = (string)$request->request->get('MessageSid', '');
        if ($sid && method_exists($this->store,'seen') && method_exists($this->store,'markSeen')) {
            if ($this->store->seen($sid)) return $this->twiml($this->brandHeader()."(reçu)");
            $this->store->markSeen($sid);
        }

        $brand = $this->brandHeader();
        $state = $this->store->get($from) ?? [
            'items'        => [],
            'type_service' => null,
            'address'      => null,
            'name'         => null,
            'phone'        => null,
            'customization_hint' => [],
            'total'        => 0.0,
            'step'         => 'draft',
        ];

        // =================== Réponses instantanées (sans IA) ===================
        if (preg_match('~^(salut|salam|slm|bonjour|bonsoir|hey|coucou|hello|السلام|مرحبا)\b~u', $lower)) {
            return $this->twiml($brand."Bienvenue ! Tape *menu* pour voir la carte ou envoie tes plats : *Pizza Margherita x2, Oulmès x1*.");
        }

        if (preg_match('~\b(menu|voir\s+le\s+menu|القائمة|المنيو)\b~u', $lower)) {
            $rows = $this->menu->getMenu();
            if (!$rows) return $this->twiml($brand."Le menu est indisponible pour le moment.");

            $byCat = [];
            foreach ($rows as $r) {
                if (isset($r['disponible']) && !$r['disponible']) continue;
                $cat = $r['category_name'] ?: 'Autres';
                $byCat[$cat][] = [$r['name'] ?? $r['nom'] ?? '', (float)($r['price'] ?? $r['prix'] ?? 0)];
            }
            if (empty($byCat)) return $this->twiml($brand."Le menu est indisponible pour le moment.");

            ksort($byCat, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($byCat as &$items) usort($items, fn($a,$b)=>strcasecmp($a[0],$b[0]));
            unset($items);

            $out = ["📋 Menu :"];
            foreach ($byCat as $cat => $items) {
                $out[] = "\n*".$cat."*";
                foreach ($items as [$name, $price]) {
                    if (!$name) continue;
                    $out[] = "• {$name} — ".number_format($price, 2)." MAD";
                }
            }
            $out[] = "\nPour commander : *NomDuPlat xQuantité* (ex: *Pizza Margherita x2*)";
            return $this->twiml($brand.implode("\n", $out));
        }
        // ======================================================================

        // =================== Parsing / mise à jour de l’état ===================
        $menuRows  = $this->menu->getMenu() ?: [];
        $menuIndex = $this->indexMenu($menuRows);

        // 1) Détection type de service
        $service = $this->detectService($lower);
        if ($service) {
            $state['type_service'] = $service;
            $state['step'] = 'await_items';
        }

        // 2) Si c’est une adresse (livraison) sans mot-clé “livraison”
        if (empty($state['address']) && $this->looksLikeAddress($body)) {
            $state['address'] = trim($body);
            $state['step'] = 'await_items';
        }

        // 3) Personnalisation “k:v; k:v”
        $inlineOpts = $this->parseCustomizationPairs($body);
        if (!empty($inlineOpts)) {
            $state['customization_hint'] = $inlineOpts;
            // si on a déjà des items en mémoire → applique au dernier
            if (!empty($state['items'])) {
                $last = array_key_last($state['items']);
                $state['items'][$last]['customizations'] = $this->mergeCustom($state['items'][$last]['customizations'] ?? [], $inlineOpts);
            }
        }

        // 4) Items “Nom xQte” présents dans ce message ?
        $parsedNow = $this->parseItemsAgainstMenu($body, $menuIndex);
        if (!empty($parsedNow)) {
            // si options inline dans le même message → colle au dernier item parsé-now
            if (!empty($inlineOpts)) {
                $lastIdx = array_key_last($parsedNow);
                $parsedNow[$lastIdx]['customizations'] = $this->mergeCustom($parsedNow[$lastIdx]['customizations'] ?? [], $inlineOpts);
            }

            // merge avec l’état : si même nom, additionne la quantité
            foreach ($parsedNow as $newIt) {
                $merged = false;
                foreach ($state['items'] as &$oldIt) {
                    if (mb_strtolower($oldIt['name']) === mb_strtolower($newIt['name'])) {
                        $oldIt['quantity'] += $newIt['quantity'];
                        $oldIt['base_unit_price']  = $newIt['base_unit_price']; // garde le dernier prix du menu
                        // merge custom
                        if (!empty($newIt['customizations'])) {
                            $oldIt['customizations'] = $this->mergeCustom($oldIt['customizations'] ?? [], $newIt['customizations']);
                        }
                        $merged = true;
                        break;
                    }
                }
                unset($oldIt);
                if (!$merged) $state['items'][] = $newIt;
            }
        }

        // Recalcule les prix (delta=0 ici; hook pour deltas si PersonalizationService les fournit)
        $subtotal = 0.0;
        foreach ($state['items'] as &$it) {
            $base  = (float)($it['base_unit_price'] ?? $it['final_unit_price'] ?? 0);
            $delta = (float)($it['delta_unit_price'] ?? 0);
            // TODO: si tu veux majorer selon supplements, calcule $delta ici avec $this->perso / BDD
            $it['final_unit_price'] = $base + $delta;
            $subtotal += ((int)$it['quantity']) * $it['final_unit_price'];
        }
        unset($it);
        $state['total'] = round($subtotal,2);

        // Sauvegarde immédiate de l’état
        $this->store->set($from, $state);

        // ===================== Confirmation “je confirme” ======================
        if ($this->looksLikeConfirm($body)) {
            // On reconstruit un aiPayload à partir de l’état (même si ce message ne contient pas d’items)
            $aiPayload = $this->payloadFromState($from, $state);

            // Besoin d’adresse si livraison ?
            if (($aiPayload['order']['service_type'] ?? null) === 'LIVRAISON' && empty(trim((string)$aiPayload['order']['delivery_address']))) {
                return $this->twiml($brand."Pour *livraison*, donne l’adresse complète (quartier, rue, numéro).");
            }

            // Garde-fou
            $errors = $this->guard->validate($aiPayload);
            if (!empty($errors)) {
                return $this->twiml($brand."🔎 Il manque :\n- ".implode("\n- ", $errors));
            }

            // Persistance
            try {
                @file_put_contents(
                    $this->getParameter('kernel.project_dir').'/var/log/last_ai_payload.json',
                    json_encode($aiPayload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                );
                $this->em->beginTransaction();
                $cmd = $this->mapper->buildCommandeFromPayload($aiPayload);
                $this->em->persist($cmd);
                $this->em->flush();
                $this->em->commit();
                $this->em->refresh($cmd);
            } catch (\Throwable $e) {
                $this->em->rollback();
                $logger->error('[ORDER_PERSIST_FAIL]', ['error' => $e->getMessage()]);
                return $this->twiml($brand."Problème d’enregistrement. Réessaie ou envoie *annuler*.");
            }

            // reset et réponse finale
            $this->store->reset($from);
            $reply = $brand."✅ Commande confirmée.\n🎉 Réf: ".$cmd->getReference()." — Total: ".$cmd->getFormattedTotal();
            return $this->twiml($reply);
        }
        // ======================================================================

        // ===================== Si on a déjà des items =========================
        if (!empty($state['items'])) {
            // S’il manque le service
            if (empty($state['type_service'])) {
                return $this->twiml($brand."Tu préfères *SUR_PLACE*, *EMPORTER* ou *LIVRAISON* ?");
            }
            // S’il faut une adresse
            if ($state['type_service']==='LIVRAISON' && empty($state['address'])) {
                return $this->twiml($brand."Pour *livraison*, indique l’adresse complète (quartier, rue, numéro).");
            }
            // Tout y est → récap + demande de confirmation
            $txt = $this->buildRecapText($state['items'], $state['type_service'], $state['address'], (float)$state['total']);
            return $this->twiml($brand.$txt);
        }
        // ======================================================================

        // ===================== Secours IA si rien compris ======================
        $system = @file_get_contents($this->getParameter('kernel.project_dir').'/var/prompts/order_system.txt');
        if (!$system) {
            $system =
                "Tu es un assistant de commande WhatsApp pour K&I Restaurant. "
              . "Langue = celle du client (darija/ar/fr/en), réponses brèves et naturelles. "
              . "Aide : menu, type de service {SUR_PLACE, EMPORTER, LIVRAISON}, adresse (si livraison), nom & téléphone si nécessaire. "
              . "Ne redemande pas une info déjà connue (service, adresse...). "
              . "Toujours produire un récap clair (articles, options, service, total) puis demander confirmation. "
              . "assistant_text = texte humain court (jamais de JSON). Pose UNE question à la fois.";
        }

        $catalogs = method_exists($this->perso,'getAllCatalogs') ? $this->perso->getAllCatalogs() : [];
        $fallbackVisible = $brand."Je peux t’aider : *menu* pour voir la carte, ou *Nom xQte* (ex: *Pizza Margherita x2*).";

        try {
            $aiPayload = $this->groq->infer([
                'from'            => $from,
                'user_text'       => $body,
                'state'           => $state,
                'menu'            => $menuRows,
                'catalogs'        => $catalogs,
                'system_prompt'   => $system,
                'timeout_seconds' => 6,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[IA_CALL_FAIL]', ['err'=>$e->getMessage()]);
            return $this->twiml($fallbackVisible);
        }

        $assistantText = $this->sanitizeAssistantText($aiPayload['assistant_text'] ?? null);
        if (!isset($aiPayload['conversation'], $aiPayload['order'], $aiPayload['actions'])) {
            return $this->twiml($assistantText ?: $fallbackVisible);
        }

        // Normalisations essentielles
        if (!empty($aiPayload['order']['service_type'])) {
            $aiPayload['order']['service_type'] = strtoupper($aiPayload['order']['service_type']);
        } elseif (!empty($state['type_service'])) {
            $aiPayload['order']['service_type'] = strtoupper($state['type_service']);
        }
        $digits = preg_replace('~\D+~', '', $from);
        if (empty($aiPayload['order']['customer']['phone']) && $digits) {
            $aiPayload['order']['customer']['phone'] = $digits;
        }
        if (empty($aiPayload['order']['customer']['name'])) {
            $aiPayload['order']['customer']['name'] = $state['name'] ?? 'WhatsApp Client';
        }

        // Si items mais pas confirmé → récap auto
        $hasItems = !empty($aiPayload['order']['items']);
        $isConfirmed = ($aiPayload['actions']['confirmed'] ?? false) === true;
        if ($hasItems && !$isConfirmed && !$assistantText) {
            $svc  = $aiPayload['order']['service_type'] ?? '';
            $addr = trim((string)($aiPayload['order']['delivery_address'] ?? ''));
            $lines = [];
            $sum = 0.0;
            foreach ($aiPayload['order']['items'] as $it) {
                $q = (int)($it['quantity'] ?? 1);
                $p = (float)($it['final_unit_price'] ?? $it['base_unit_price'] ?? 0);
                $sum += $q*$p;
                $optTxt = '';
                if (!empty($it['customizations']) && is_array($it['customizations'])) {
                    $kv = [];
                    foreach ($it['customizations'] as $k=>$v) {
                        if (is_array($v)) $v = implode(', ', $v);
                        $kv[] = $k.': '.$v;
                    }
                    if ($kv) $optTxt = ' ('.implode(', ', $kv).')';
                }
                $lines[] = "- {$q}× {$it['name']}{$optTxt} — ".number_format($q*$p, 2)." MAD";
            }
            $total = (float)($aiPayload['order']['totals']['grand_total'] ?? $sum);
            $svcLabel = $svc ? ("*Service* : ".str_replace('_',' ',ucfirst(strtolower($svc)))."\n") : '';
            $addrLine = ($svc === 'LIVRAISON' && $addr) ? "*Adresse* : {$addr}\n" : '';
            $assistantText =
                "🧾 Récap commande :\n".
                implode("\n", $lines)."\n".
                $svcLabel.$addrLine.
                "*Total* : ".number_format($total,2)." MAD\n\n".
                "Confirmez ? (répondez *je confirme* ou *ok*)";
        }

        // Mise à jour mémoire avec ce que l’IA a compris
        $this->store->set($from, [
            'from'         => $from,
            'step'         => $aiPayload['conversation']['status'] ?? 'draft',
            'items'        => $aiPayload['order']['items'] ?? [],
            'type_service' => $aiPayload['order']['service_type'] ?? ($state['type_service'] ?? null),
            'address'      => $aiPayload['order']['delivery_address'] ?? null,
            'name'         => $aiPayload['order']['customer']['name'] ?? null,
            'phone'        => $aiPayload['order']['customer']['phone'] ?? null,
            'total'        => $aiPayload['order']['totals']['grand_total'] ?? 0.0,
            'customization_hint' => $state['customization_hint'] ?? [],
        ]);

        // Besoin d’adresse ?
        if (($aiPayload['order']['service_type'] ?? null) === 'LIVRAISON' &&
            empty(trim((string)($aiPayload['order']['delivery_address'] ?? '')))) {
            return $this->twiml($brand."Pour *livraison*, j’ai besoin de l’adresse complète (quartier, rue, numéro).");
        }

        // Garde-fou
        $errors = $this->guard->validate($aiPayload);
        if (!empty($errors) && !$isConfirmed) {
            $visible = $assistantText ?: ("🔎 Il manque :\n- ".implode("\n- ", $errors));
            return $this->twiml($brand.$visible);
        }

        // Confirmé → enregistrer
        if ($isConfirmed) {
            try {
                @file_put_contents(
                    $this->getParameter('kernel.project_dir').'/var/log/last_ai_payload.json',
                    json_encode($aiPayload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                );
                $this->em->beginTransaction();
                $cmd = $this->mapper->buildCommandeFromPayload($aiPayload);
                $this->em->persist($cmd);
                $this->em->flush();
                $this->em->commit();
                $this->em->refresh($cmd);
            } catch (\Throwable $e) {
                $this->em->rollback();
                $logger->error('[ORDER_PERSIST_FAIL]', ['error' => $e->getMessage()]);
                return $this->twiml($brand."Problème d’enregistrement. Réessaie ou envoie *annuler*.");
            }

            $this->store->reset($from);
            $reply = $brand."✅ Commande confirmée.\n🎉 Réf: ".$cmd->getReference()." — Total: ".$cmd->getFormattedTotal();
            return $this->twiml($reply);
        }

        return $this->twiml($brand.($assistantText ?: $fallbackVisible));
    }

    // ==================== Helpers ====================

    private function brandHeader(): string
    {
        return "🍽️ K&I Restaurant\n\n";
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

    private function twiml(string $message, int $status = 200): Response
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
            "<Response>\n".
            "  <Message>".htmlspecialchars($message, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</Message>\n".
            "</Response>";
        return new Response($xml, $status, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    private function sanitizeAssistantText(?string $txt): string
    {
        if (!is_string($txt) || $txt === '') return '';
        if (preg_match('/^\s*[\{\[]/u', $txt)) {
            $decoded = json_decode($txt, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (!empty($decoded['assistant_text']) && is_string($decoded['assistant_text'])) {
                    return $decoded['assistant_text'];
                }
            }
            return "Bien noté. Tu préfères SUR_PLACE, EMPORTER ou LIVRAISON ?";
        }
        if (strlen($txt) > 0 && str_contains($txt, '{') && str_contains($txt, '}')) {
            return "Bien noté. Tu préfères SUR_PLACE, EMPORTER ou LIVRAISON ?";
        }
        return $txt;
    }

    /** Construit un aiPayload “confirmed” à partir de l’état courant */
    private function payloadFromState(string $from, array $state): array
    {
        $digits = preg_replace('~\D+~', '', $from);
        $items  = [];
        $subtotal = 0.0;
        foreach ($state['items'] as $it) {
            $q = (int)$it['quantity'];
            $base  = (float)($it['base_unit_price'] ?? $it['final_unit_price'] ?? 0);
            $delta = (float)($it['delta_unit_price'] ?? 0);
            $final = $base + $delta;
            $subtotal += $q * $final;
            $items[] = [
                'name'             => $it['name'],
                'quantity'         => $q,
                'base_unit_price'  => $base,
                'delta_unit_price' => $delta,
                'final_unit_price' => $final,
                'customizations'   => $it['customizations'] ?? [],
            ];
        }
        return [
            'assistant_text' => "Commande confirmée.",
            'conversation'   => ['from'=>$from,'language'=>'fr','status'=>'confirmed'],
            'order' => [
                'items'            => $items,
                'service_type'     => strtoupper((string)($state['type_service'] ?? '')),
                'delivery_address' => $state['address'] ?? null,
                'customer'         => ['name'=>$state['name'] ?? 'WhatsApp Client', 'phone'=>$digits],
                'currency'         => 'MAD',
                'totals'           => [
                    'items_subtotal' => round($subtotal,2),
                    'delivery_fee'   => 0,
                    'grand_total'    => round($subtotal,2),
                ],
            ],
            'actions' => [
                'need_service_type'      => empty($state['type_service']),
                'need_address'           => ($state['type_service'] ?? '') === 'LIVRAISON' && empty(trim((string)($state['address'] ?? ''))),
                'need_customer_info'     => false,
                'ready_for_confirmation' => true,
                'confirmed'              => true,
                'cancelled'              => false,
            ],
        ];
    }

    // -------- Parsing local robuste --------

    private function indexMenu(array $rows): array
    {
        $idx = [];
        foreach ($rows as $r) {
            if (isset($r['disponible']) && !$r['disponible']) continue;
            $name = $r['name'] ?? $r['nom'] ?? '';
            if ($name === '') continue;
            $idx[$this->norm($name)] = [
                'id'    => (int)($r['id'] ?? 0),
                'name'  => $name,
                'price' => (float)($r['price'] ?? $r['prix'] ?? 0),
            ];
        }
        return $idx;
    }

    private function detectService(string $lower): ?string
    {
        if (preg_match('~\b(livraison|livrer|delivery|توصيل)\b~u', $lower)) return 'LIVRAISON';
        if (preg_match('~\b(emporter|à\s*emporter|a\s*emporter|take\s*away|تيك\s*اواي)\b~u', $lower)) return 'EMPORTER';
        if (preg_match('~\b(sur\s*place|على\s*المكان|هنا)\b~u', $lower)) return 'SUR_PLACE';
        return null;
    }

    /** heuristique simple d’adresse */
    private function looksLikeAddress(string $text): bool
    {
        return (bool)preg_match('~\b(rue|bd|avenue|quartier|حي|زنقة|douar|n°|numero|numéro|marrakech|casablanca|agadir)\b~iu', $text);
    }

    private function parseCustomizationPairs(string $text): array
    {
        $pairs = [];
        $chunks = preg_split('~[;\n]+~u', $text);
        foreach ($chunks as $c) {
            if (!str_contains($c, ':')) continue;
            [$k,$v] = array_map('trim', explode(':', $c, 2));
            if ($k === '' || $v === '') continue;
            $k = mb_strtolower($k);
            $k = strtr($k, ['pâte'=>'pate']);
            $vals = array_map('trim', preg_split('~[,،]+~u', $v));
            $vals = array_values(array_filter($vals, fn($x)=>$x!==''));
            if (empty($vals)) continue;
            $pairs[$k] = count($vals) === 1 ? $vals[0] : $vals;
        }
        return $pairs;
    }

    private function mergeCustom(array $a, array $b): array
    {
        foreach ($b as $k=>$v) $a[$k] = $v;
        return $a;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe']);
        $s = preg_replace('~[^a-z0-9 ]+~',' ',$s);
        $s = preg_replace('~\s+~',' ',$s);
        return trim($s);
    }

    private function parseItemsAgainstMenu(string $text, array $menuIndex): array
    {
        // supporte "’ai choisi ..." en retirant un éventuel début bruité
        $text = preg_replace("~^[’'`]+ai choisi\\s+~iu", "", $text);

        $parts = preg_split('~[,;\n]|\\bet\\b~iu', $text);
        $items = [];
        foreach ($parts as $raw) {
            $raw = trim($raw);
            if ($raw==='') continue;

            if (preg_match('~^(.*?)(?:\\s*[xX]\\s*(\\d+))$~u', $raw, $m)) {
                $name = trim($m[1]);
                $qty  = max(1, (int)$m[2]);
            } else {
                continue;
            }
            $n = $this->norm($name);

            $match = $menuIndex[$n] ?? null;
            if (!$match) {
                $n2 = preg_replace('~^(pizza|boisson|burger)\\s+~u','', $n);
                $match = $menuIndex[$n2] ?? null;
            }
            if (!$match) continue;

            $items[] = [
                'name'             => $match['name'],
                'quantity'         => $qty,
                'base_unit_price'  => (float)$match['price'],
                'delta_unit_price' => 0.0,
                'final_unit_price' => (float)$match['price'],
                'customizations'   => [],
            ];
        }
        return $items;
    }

    private function looksLikeConfirm(string $text): bool
    {
        return (bool)preg_match('~\\b(je\\s*confirme|confirme|ok|oui|d[’\'e]accord|daccord|yes|confirm|ايوا|نعم|خلاص|تمام)\\b~iu', $text);
    }

    private function buildRecapText(array $items, ?string $service, ?string $address, float $subtotal): string
    {
        $lines = ["🧾 Récap commande :"];
        foreach ($items as $it) {
            $q = (int)$it['quantity'];
            $p = (float)$it['final_unit_price'];
            $opts = '';
            if (!empty($it['customizations'])) {
                $kv = [];
                foreach ($it['customizations'] as $k=>$v) {
                    if (is_array($v)) $v = implode(', ', $v);
                    $kv[] = "$k: $v";
                }
                if ($kv) $opts = ' ('.implode(', ', $kv).')';
            }
            $lines[] = "- {$q}× {$it['name']}{$opts} — ".number_format($q*$p,2)." MAD";
        }
        if ($service) $lines[] = "*Service* : ".str_replace('_',' ',ucfirst(strtolower($service)));
        if ($service==='LIVRAISON' && $address) $lines[] = "*Adresse* : {$address}";
        $lines[] = "*Total* : ".number_format($subtotal,2)." MAD";
        $lines[] = "";
        $lines[] = "Confirmez ? (répondez *je confirme* ou *ok*)";
        return implode("\n", $lines);
    }

    // ---------------- PING debug ----------------
    #[Route('/webhook/ping', name: 'whatsapp_ping', methods: ['POST','GET'])]
    public function ping(Request $request): Response
    {
        error_log('[PING] hit method='.$request->getMethod().' t='.date('c'));
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
            "<Response><Message>PONG ✅</Message></Response>";
        return new Response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
