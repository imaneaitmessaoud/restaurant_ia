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
use App\Service\GroqOrderAgent;   // optionnel (filet IA)
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
        // --- Vérif Twilio (facultatif)
        $verify    = ($_ENV['TWILIO_VERIFY'] ?? 'false') === 'true';
        $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        if ($verify) {
            if (!$authToken) return $this->twiml('Server misconfigured', 500);
            if (!$this->isTwilioRequest($request, $authToken)) return $this->twiml('Unauthorized', 403);
        }

        $from  = (string)$request->request->get('From', 'unknown'); // ex: whatsapp:+2126...
        $body  = trim((string)$request->request->get('Body', ''));
        $lower = mb_strtolower($body);
        $brand = $this->brandHeader();

        $logger->info('[WHATSAPP] hit', ['from'=>$from,'body'=>$body,'t'=>date('c')]);

        // Rien reçu
        $numMedia = (int)$request->request->get('NumMedia', 0);
        if ($body === '' && $numMedia === 0) {
            return $this->twiml($brand."Écris par ex : *Pizza Margherita x2* ou *menu*.");
        }

        // Idempotence douce
        $sid = (string)$request->request->get('MessageSid', '');
        if ($sid && method_exists($this->store,'seen') && method_exists($this->store,'markSeen')) {
            if ($this->store->seen($sid)) return $this->twiml($brand."(reçu)");
            $this->store->markSeen($sid);
        }

        // État
        $state = $this->store->get($from) ?? [
            'items'        => [],
            'type_service' => null,
            'address'      => null,
            'name'         => null,
            'phone'        => null,
            'total'        => 0.0,
            'step'         => 'draft',
        ];

        // =================== RÉPONSES INSTANTANÉES ===================

        // A) Salutations
        if (preg_match('~^(salut|salam|slm|bonjour|bonsoir|hey|coucou|hello|السلام|مرحبا)\b~u', $lower)) {
            return $this->twiml($brand."Bienvenue ! Tape *menu* pour voir la carte, ou écris tes plats : *Pizza Margherita x2, Oulmès x1*.");
        }

        // B) ANNULATION (PRIORITAIRE)
        if ($this->looksLikeCancel($lower)) {
            // on vide l’état (mémoire courte)
            $this->store->reset($from);
            return $this->twiml($brand."❌ Commande annulée. Si tu veux recommencer, envoie *menu* ou un plat (ex: *Margherita x1*).");
        }

        // C) “menu” depuis la BDD
        if (preg_match('~\b(menu|voir\s+le\s+menu|القائمة|المنيو)\b~u', $lower)) {
            $rows = $this->menu->getMenu();
            if (!$rows) return $this->twiml($brand."Le menu est indisponible pour le moment.");

            // Grouper par catégorie
            $byCat = [];
            foreach ($rows as $r) {
                if (isset($r['disponible']) && !$r['disponible']) continue;
                $cat  = $r['category_name'] ?? 'Autres';
                $name = $r['name'] ?? $r['nom'] ?? '';
                $price= (float)($r['price'] ?? $r['prix'] ?? 0);
                if ($name === '') continue;
                $byCat[$cat][] = [$name, $price];
            }
            if (empty($byCat)) return $this->twiml($brand."Le menu est indisponible pour le moment.");

            ksort($byCat, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($byCat as &$items) usort($items, fn($a,$b)=>strcasecmp($a[0],$b[0]));
            unset($items);

            $out = ["📋 Menu :"];
            foreach ($byCat as $cat => $items) {
                $out[] = "\n*".$cat."*";
                foreach ($items as [$name, $price]) {
                    $out[] = "• {$name} — ".number_format($price, 2)." MAD";
                }
            }
            $out[] = "\nPour commander : *NomDuPlat xQuantité* (ex: *Pizza Margherita x2*)";
            return $this->twiml($brand.implode("\n", $out));
        }

        // =============== PARSING LOCAL (items + options) ===============
        $menuRows  = $this->menu->getMenu() ?: [];
        $menuIndex = $this->indexMenu($menuRows);

        // 1) Service si clairement dit
        $detectedService = $this->detectService($lower);
        if ($detectedService) {
            $state['type_service'] = $detectedService;
            $state['step'] = 'await_items';
        }

        // 2) Adresse si livraison
        if ($state['type_service'] === 'LIVRAISON' && empty($state['address']) && $this->looksLikeAddress($body)) {
            $state['address'] = trim($body);
            $state['step'] = 'await_items';
        }

        // 3) Paires d’options (k:v; k:v)
        $inlineOpts = $this->parseCustomizationPairs($body);
        if (!empty($inlineOpts) && !empty($state['items'])) {
            $last = array_key_last($state['items']);
            $state['items'][$last]['customizations'] =
                $this->mergeCustom($state['items'][$last]['customizations'] ?? [], $inlineOpts);
        }

        // 4) Items “Nom xQte” dans ce message
        $parsedNow = $this->parseItemsAgainstMenu($body, $menuIndex);
        if (!empty($parsedNow)) {
            if (!empty($inlineOpts)) {
                $lastIdx = array_key_last($parsedNow);
                $parsedNow[$lastIdx]['customizations'] =
                    $this->mergeCustom($parsedNow[$lastIdx]['customizations'] ?? [], $inlineOpts);
            }
            foreach ($parsedNow as $newIt) {
                $merged = false;
                foreach ($state['items'] as &$oldIt) {
                    if ((int)($oldIt['menu_item_id'] ?? 0) === (int)($newIt['menu_item_id'] ?? -1)) {
                        $oldIt['quantity'] += $newIt['quantity'];
                        $oldIt['base_unit_price'] = $newIt['base_unit_price'];
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

        // ======= OPTIONS OBLIGATOIRES EN PRIORITÉ =======
        $missingIdx = $this->findFirstItemMissingRequired($state['items']);
        if ($missingIdx !== null) {
            if (!empty($inlineOpts)) {
                $state['items'][$missingIdx]['customizations'] =
                    $this->mergeCustom($state['items'][$missingIdx]['customizations'] ?? [], $inlineOpts);
                $missingIdx = $this->findFirstItemMissingRequired($state['items']);
            }
            $this->store->set($from, $state);
            if ($missingIdx !== null) {
                return $this->twiml($brand.$this->firstMissingPrompt($state['items'][$missingIdx]));
            }
        }

        // ======= Recalcule prix (delta via PersonalizationService + taille) =======
        $subtotal = 0.0;
        foreach ($state['items'] as &$it) {
            $base  = (float)($it['base_unit_price'] ?? $it['final_unit_price'] ?? 0);
            $mid   = (int)($it['menu_item_id'] ?? 0);
            $catalog = $mid ? $this->perso->getCatalogForItemId($mid) : [];

            // 1) Delta standard depuis customizations
            $delta = $this->perso->computeDeltaUnit((array)($it['customizations'] ?? []), $catalog);

            // 2) Supplément selon la taille
            $taille = $it['customizations']['taille'] ?? 'petite';
            $taille = mb_strtolower(trim($taille));
            switch ($taille) {
                case 'moyenne':
                    $delta += 5.0;
                    break;
                case 'grande':
                    $delta += 10.0;
                    break;
                case 'petite':
                default:
                    $delta += 0.0;
            }

            $it['delta_unit_price'] = $delta;
            $it['final_unit_price'] = $base + $delta;
            $subtotal += ((int)$it['quantity']) * $it['final_unit_price'];
        }
        unset($it);

        // Mettre à jour le total dans l'état
        $state['total'] = round($subtotal, 2);
        $this->store->set($from, $state);


        // ======= Confirmation ? =======
        if ($this->looksLikeConfirm($body)) {
            $aiPayload = $this->payloadFromState($from, $state);

            if (($aiPayload['order']['service_type'] ?? null) === 'LIVRAISON' &&
                empty(trim((string)$aiPayload['order']['delivery_address'] ?? ''))) {
                return $this->twiml($brand."Pour *livraison*, donne l’adresse complète (quartier, rue, numéro).");
            }

            $errors = $this->guard->validate($aiPayload);
            if (!empty($errors)) {
                return $this->twiml($brand."🔎 Il manque :\n- ".implode("\n- ", $errors));
            }

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

        // ======= Si on a des items, guider vers service/adresse puis récap =======
        if (!empty($state['items'])) {
            if (empty($state['type_service'])) {
                return $this->twiml($brand."Tu préfères *SUR_PLACE*, *EMPORTER* ou *LIVRAISON* ?");
            }
            if ($state['type_service']==='LIVRAISON' && empty($state['address'])) {
                return $this->twiml($brand."Pour *livraison*, indique l’adresse complète (quartier, rue, numéro).");
            }
            $txt = $this->buildRecapText($state['items'], $state['type_service'], $state['address'], (float)$state['total']);
            return $this->twiml($brand.$txt);
        }

        // ======= Filet IA (facultatif) =======
        $system = @file_get_contents($this->getParameter('kernel.project_dir').'/var/prompts/order_system.txt');
        if (!$system) {
            $system =
                "Tu es un assistant de commande WhatsApp pour K&I Restaurant. "
              . "Langue = celle du client, réponses brèves. "
              . "Aide : menu, service {SUR_PLACE, EMPORTER, LIVRAISON}, adresse si livraison. "
              . "Toujours récap avant confirmation. assistant_text = texte humain (pas de JSON).";
        }

        $fallbackVisible = $brand."Je peux t’aider : *menu* pour voir la carte, ou *Nom xQte* (ex: *Pizza Margherita x2*).";
        try {
            $aiPayload = $this->groq->infer([
                'from'            => $from,
                'user_text'       => $body,
                'state'           => $state,
                'menu'            => $menuRows,
                'catalogs'        => [],
                'system_prompt'   => $system,
                'timeout_seconds' => 5,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[IA_CALL_FAIL]', ['err'=>$e->getMessage()]);
            return $this->twiml($fallbackVisible);
        }

        $assistantText = $this->sanitizeAssistantText($aiPayload['assistant_text'] ?? null);
        if (!isset($aiPayload['conversation'], $aiPayload['order'], $aiPayload['actions'])) {
            return $this->twiml($assistantText ?: $fallbackVisible);
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
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
             . "<Response>\n"
             . "  <Message>".htmlspecialchars($message, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</Message>\n"
             . "</Response>";
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
        if (str_contains($txt, '{') && str_contains($txt, '}')) {
            return "Bien noté. Tu préfères SUR_PLACE, EMPORTER ou LIVRAISON ?";
        }
        return $txt;
    }

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
                'menu_item_id'     => (int)($it['menu_item_id'] ?? 0),
                'name'             => (string)$it['name'],
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

    private function parseItemsAgainstMenu(string $text, array $menuIndex): array
    {
        // nettoie “j’ai choisi …”
        $text = preg_replace("~^[’'`]?j?[’'` ]*ai choisi\\s+~iu", "", $text);

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
                $n2 = preg_replace('~^(pizza|boisson|burger|p\\xC3\\xA2te|pate)\\s+~u','', $n);
                $match = $menuIndex[$n2] ?? null;
            }
            if (!$match) continue;

            $items[] = [
                'menu_item_id'     => (int)$match['id'],
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

    private function findFirstItemMissingRequired(array $items): ?int
    {
        foreach ($items as $i => $it) {
            $mid = (int)($it['menu_item_id'] ?? 0);
            if ($mid <= 0) continue;
            $catalog = $this->perso->getCatalogForItemId($mid);
            if (!$catalog) continue;
            $custom = (array)($it['customizations'] ?? []);
            $check  = $this->perso->checkRequiredFilled($custom, $catalog);
            if (!($check['ok'] ?? false)) return $i;
        }
        return null;
    }

    private function firstMissingPrompt(array $item): string
    {
        $name = (string)($item['name'] ?? 'cet article');
        $mid  = (int)($item['menu_item_id'] ?? 0);
        $catalog = $mid ? $this->perso->getCatalogForItemId($mid) : [];
        if (!$catalog) return "Peux-tu préciser les options pour *{$name}* ?";

        $custom  = (array)($item['customizations'] ?? []);
        $check   = $this->perso->checkRequiredFilled($custom, $catalog);
        $missing = (array)($check['missing'] ?? []);

        $lines = ["Options requises pour *{$name}* :"];
        foreach ($catalog as $group) {
            $key = (string)($group['key'] ?? '');
            if (!($group['required'] ?? false) || !in_array($key, $missing, true)) continue;
            $label = (string)($group['label'] ?? ucfirst($key));
            $vals  = implode(', ', array_keys((array)$group['values']));
            $lines[] = "- {$label} : {$vals}";
        }
        $lines[] = "";
        $lines[] = "Réponds par ex : *taille: Grande; pate: Fine*";
        return implode("\n", $lines);
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
                    $kv[] = ucfirst($k).": $v";
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


    private function detectService(string $lower): ?string
    {
        if (preg_match('~\b(livraison|livrer|delivery|توصيل)\b~u', $lower)) return 'LIVRAISON';
        if (preg_match('~\b(emporter|à\s*emporter|a\s*emporter|take\s*away|تيك\s*اواي)\b~u', $lower)) return 'EMPORTER';
        if (preg_match('~\b(sur\s*place|على\s*المكان|هنا)\b~u', $lower)) return 'SUR_PLACE';
        return null;
    }

    private function looksLikeAddress(string $text): bool
    {
        return (bool)preg_match('~\b(rue|bd|avenue|quartier|حي|زنقة|douar|n°|numero|numéro|marrakech|casablanca|agadir)\b~iu', $text);
    }

    private function looksLikeConfirm(string $text): bool
    {
        return (bool)preg_match('~\\b(je\\s*confirme|confirme|ok|oui|d[’\'e]accord|daccord|yes|confirm|ايوا|نعم|خلاص|تمام)\\b~iu', $text);
    }

    private function looksLikeCancel(string $lower): bool
    {
        // FR/EN/AR + variantes
        return (bool)preg_match('~\\b(annuler|j\\s*annule|cancel|stop|reset|لا|ماعجبنيش|ما\\s*بغيتش)\\b~iu', $lower);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe']);
        $s = preg_replace('~[^a-z0-9 ]+~',' ',$s);
        $s = preg_replace('~\s+~',' ',$s);
        return trim($s);
    }

    // ---------------- PING & DEBUG ----------------

    #[Route('/webhook/ping', name: 'whatsapp_ping', methods: ['POST','GET'])]
    public function ping(Request $request): Response
    {
        error_log('[PING] hit method='.$request->getMethod().' t='.date('c'));
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
             . "<Response><Message>PONG ✅</Message></Response>";
        return new Response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    #[Route('/debug/menu', name: 'debug_menu', methods: ['GET'])]
    public function debugMenu(): Response
    {
        return $this->json([
            'source' => 'db',
            'menu'   => $this->menu->getMenu(),
        ]);
    }
}
