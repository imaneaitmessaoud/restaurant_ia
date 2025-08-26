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

class WhatsappOrderController extends AbstractController
{
    public function __construct(
        private MenuProvider $menu,
        private ConversationStore $store,
        private RecommendationService $reco,
        private EntityManagerInterface $em,
        private InfoProvider $info
    ) {}

    #[Route('/webhook/whatsapp', name: 'whatsapp_order', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        // --- Vérification Twilio (facultatif selon ton .env) ---
        $verify   = ($_ENV['TWILIO_VERIFY'] ?? 'true') === 'true';
        $authToken= $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        if ($verify) {
            if (!$authToken) {
                return $this->twiml('Server misconfigured', 500);
            }
            if (!$this->isTwilioRequest($request, $authToken)) {
                return $this->twiml('Unauthorized', 403);
            }
        }

        $from = $request->request->get('From', 'unknown');     // e.g. "whatsapp:+2126xxxxxxx"
        $body = trim((string)$request->request->get('Body', ''));
        $lower= mb_strtolower($body);

        // Numéro normalisé (ex: 2126xxxxxxx)
        $digits = preg_replace('~\D+~', '', $from) ?: '0000';

        // Récupération état conversation
        $state = $this->store->get($from);

        // ---------- SCÉNARIOS D'INFO (réponses directes) ----------
        if (preg_match('~^(aide|help|bonjour|salut|info|informations?)\b~i', $lower)) {
            return $this->twiml($this->info->intro());
        }
        if (preg_match('~(horaire|ouvert|fermé|ferme|ouverture)~i', $lower)) {
            return $this->twiml($this->info->horaires());
        }
        if (preg_match('~(promo|promotion|réduction|offre|happy hour)~i', $lower)) {
            return $this->twiml($this->info->promos());
        }
        if (preg_match('~(wifi|wi[- ]?fi|service|carte|paiement|clim|prise|ambiance)~i', $lower)) {
            return $this->twiml($this->info->services());
        }
        if (preg_match('~(allerg|gluten|lactose|végan|végéta)~i', $lower)) {
            return $this->twiml($this->info->allergenes());
        }
        if (preg_match('~(livraison|livrez|frais|zone|d[ée]lai)~i', $lower)) {
            return $this->twiml($this->info->livraisonZones());
        }
        if (preg_match('~(conseil|recomman|id[ée]e)~i', $lower)) {
            return $this->twiml($this->info->conseilsIntro());
        }
        if (preg_match('~(r[ée]serv|table|anniversaire)~i', $lower)) {
            return $this->twiml($this->info->reservation());
        }
        if (preg_match('~(contact|t[ée]l[ée]phone|email|mail)~i', $lower)) {
            return $this->twiml($this->info->infosContact());
        }

        // ---------- SUIVI DE COMMANDE PAR RÉFÉRENCE ----------
        // ex: "où en est ma commande CMD-000123"
        if (preg_match('~(où|ou|statut|suivi).*(commande|cmd)[^\d]*(\d{6})~i', $lower, $m)) {
            $id = (int)$m[3];
            $cmd = $this->em->getRepository(Commande::class)->find($id);
            if (!$cmd) {
                return $this->twiml("Je ne trouve pas la commande *CMD-".sprintf('%06d',$id)."*.");
            }
            return $this->twiml(
                "📋 Statut de *CMD-".sprintf('%06d',$cmd->getId())."* : ".$cmd->getStatut()->getLabel().
                "\nTotal : ".$cmd->getFormattedTotal()
            );
        }

        // ---------- COMMANDES RAPIDES ----------
        if (preg_match('~^annuler\b~i', $body)) {
            $this->store->reset($from);
            return $this->twiml("🗑️ Commande annulée. Tapez *menu* pour recommencer.");
        }
        if (preg_match('~^modifier\b~i', $body)) {
            $state['step']  = 'await_items';
            $state['items'] = [];
            $state['total'] = 0.0;
            $this->store->set($from, $state);
            return $this->twiml("D’accord. Dites-moi ce que vous souhaitez commander (ex: *Margherita x2, Coca x1*).");
        }

        // ---------- AFFICHER MENU ----------
        if (preg_match('~\bmenu\b~i', $lower)) {
            $items = $this->menu->getMenu();
            if (!$items) {
                return $this->twiml("Désolé, le menu est momentanément indisponible.");
            }
            $txt = "📋 Voici notre menu :\n\n";
            foreach ($items as $i) {
                $txt .= "🍽 {$i['name']} — {$i['price']} MAD\n";
            }
            $state['step'] = 'await_items';
            $this->store->set($from, $state);
            return $this->twiml($txt."\n\nPour commander : *NomDuPlat xQuantité* (ex: *Margherita x2*).");
        }

        // ---------- DÉMARRAGE D’UNE COMMANDE ----------
        if (($state['step'] ?? null) === 'await_items' || preg_match('~^\s*(commander|je veux|je prends)\b~i', $body)) {
            $picked = $this->parseItems($body); // ex: "Margherita x2, Coca x1"
            if (!$picked && ($state['step'] ?? null) !== 'await_items') {
                $state['step'] = 'await_items';
                $this->store->set($from, $state);
                return $this->twiml("Dites ce que vous voulez : *Plat xQte* (ex: *Margherita x2*).");
            }

            foreach ($picked as $p) {
                $row = $this->menu->findByName($p['name']);
                if (!$row) { continue; }
                $state['items'][] = [
                    'name'  => $row['name'],
                    'qty'   => max(1, (int)$p['qty']),
                    'price' => (float)$row['price'], // prix du jour (Excel)
                ];
            }

            // total provisoire (affichage)
            $state['total'] = array_reduce(
                $state['items'] ?? [],
                fn($s, $i) => $s + ($i['qty'] * $i['price']),
                0.0
            );

            $state['step']  = 'await_service';
            $this->store->set($from, $state);

            $recap = $this->recap($state);
            return $this->twiml($recap."\n\nChoisissez *sur place*, *emporter* ou *livraison*.");
        }

        // ---------- TYPE DE SERVICE ----------
        if (($state['step'] ?? null) === 'await_service') {
            if (preg_match('~sur\s*place~i', $lower)) {
                $state['type_service'] = 'sur_place';
            } elseif (preg_match('~emporter|à emporter|a emporter~i', $lower)) {
                $state['type_service'] = 'emporter';
            } elseif (preg_match('~livraison|livrer~i', $lower)) {
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
            return $this->twiml($this->recap($state)."\n\nPour valider : *valider Nom, Téléphone*");
        }

        // ---------- ADRESSE (si livraison) ----------
        if (($state['step'] ?? null) === 'await_address') {
            if (mb_strlen($body) < 5) {
                return $this->twiml("Adresse trop courte, pouvez-vous préciser ?");
            }
            $state['address'] = $body;
            $state['step']    = 'await_confirm';
            $this->store->set($from, $state);
            return $this->twiml($this->recap($state)."\n\nPour valider : *valider Nom, Téléphone*");
        }

        // ---------- VALIDATION ----------
        if (($state['step'] ?? null) === 'await_confirm') {
            if (!preg_match('~^valider\s+([^,]+)\s*,\s*([\d\s\+]+)~i', $body, $m)) {
                return $this->twiml("Format attendu : *valider Nom, Téléphone*");
            }
            $state['name']  = trim($m[1]);
            $state['phone'] = preg_replace('~\D+~', '', $m[2]);

            // 1) User
            $user = $this->findOrCreateUser($state['name'], $state['phone']);

            // 2) Commande (tête)
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

            // 3) Lignes de commande
            $linesTotal = 0.0;
            foreach (($state['items'] ?? []) as $it) {
                $line = new CommandeItem();
                $line->setCommande($commande);
                $line->setQuantite((int)$it['qty']);
                $line->setPrixUnitaire((string)$it['price']);  // on garde le prix du jour

                // Lier MenuItem si existant en BDD (sinon laisser NULL *si* votre mapping le permet)
                $menuItem = $this->em->getRepository(MenuItem::class)->findOneBy(['nom' => $it['name']]);
                if ($menuItem) {
                    $line->setMenuItem($menuItem);
                } // ⚠ si votre mapping est JoinColumn(nullable=false) ici, il faut des MenuItem en BDD !

                // commentaire ligne : on peut stocker le nom exact proposé (optionnel)
                $line->setCommentaire($it['name']);

                // Ajoute la ligne (cascade persist OK)
                $commande->addCommandeItem($line);

                $linesTotal += ((float)$it['price']) * ((int)$it['qty']);
            }

            // 4) Total depuis les lignes (source de vérité)
            $commande->setTotal(number_format($linesTotal, 2, '.', ''));

            // 5) Snapshot JSON dans commentaire Commande
            $snapshot = [
                'items'       => $state['items'] ?? [],
                'type_service'=> $state['type_service'] ?? null,
                'address'     => $state['address'] ?? null,
                'name'        => $state['name'],
                'phone'       => $state['phone'],
                'total_calc'  => $linesTotal,
                'created_at'  => date('c'),
            ];
            $commande->setCommentaire(json_encode($snapshot, JSON_UNESCAPED_UNICODE));

            // 6) Préférences client (optionnel)
            if (method_exists($user, 'getPreference') && $user->getPreference()) {
                $user->getPreference()->setLastOrderDate(new \DateTime());
                $this->em->persist($user->getPreference());
            }

            // 7) Flush unique
            $this->em->flush();

            $ref = $commande->getReference();
            $this->store->reset($from);

            return $this->twiml(
                "🎉 Commande validée !\n".
                "- Ref: {$ref}\n".
                "- Client: {$state['name']}\n".
                "- Total: ".number_format($linesTotal, 2)." DH\n".
                "- Livraison: ".((($state['type_service'] ?? '')==='livraison') ? 'OUI' : 'NON')."\n".
                "Merci 🙏"
            );
        }

        // ---------- ACCUEIL / RECOMMANDATIONS (inactif > 30j) ----------
        $user = $this->findUserByPhone($digits);
        if ($user && $this->reco->isInactiveSince($user, 30)) {
            $tops = $this->reco->topItems($user, 3);
            if ($tops) {
                $txt = "👋 Heureux de vous revoir ! Suggestions basées sur vos précédentes commandes :\n";
                foreach ($tops as $t) { $txt .= "• {$t}\n"; }
                $txt .= "\nTapez *menu* pour voir la carte ou *commander <plat> x<qte>* pour commencer.";
                return $this->twiml($txt);
            }
        }

        // ---------- FALLBACK ----------
        return $this->twiml("Je peux vous aider à *voir le menu* (tapez *menu*) et *passer commande*.\nEx: *Margherita x2*.");
    }

    // ------------------ Helpers ------------------

    private function parseItems(string $text): array
    {
        // Supprime un éventuel préfixe "commander/je veux/je prends"
        $text = preg_replace('~^(commander|je veux|je prends)\s+~i', '', trim($text));
        // Séparer par virgule, point-virgule ou " et "
        $parts = preg_split('~[,;]| et ~i', $text);

        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;

            // "Nom xQte"
            if (preg_match('~^\s*(.+?)\s*x\s*(\d+)\s*$~u', $p, $m)) {
                $out[] = ['name' => trim($m[1]), 'qty' => (int)$m[2]];
            } else {
                // pas de "xQte" => qty=1
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
        $total = array_reduce($state['items'] ?? [], fn($s,$i)=> $s + $i['qty']*$i['price'], 0.0);
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
        $u = $this->findUserByPhone($digits);
        if ($u) return $u;

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

    private function isTwilioRequest(Request $request, string $authToken): bool
    {
        $signature = $request->headers->get('X-Twilio-Signature', '');
        $url       = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        $params = $request->request->all();
        ksort($params);

        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . $v;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return $signature && hash_equals($expected, $signature);
    }
    /** Préfixe “menu mis à jour” si le fichier Excel a changé pendant la conversation */
private function maybeMenuUpdatedPrefix(array &$state, MenuProvider $menu, string $from): string
 {
    // nécessite MenuProvider::getChecksum() (voir plus bas si tu ne l’as pas encore)
    if (!method_exists($menu, 'getChecksum')) return '';

    $checksum = $menu->getChecksum();
    if ($checksum && (($state['menu_checksum'] ?? null) !== $checksum)) {
        $state['menu_checksum'] = $checksum;
        $this->store->set($from, $state);
        return "ℹ️ Le menu vient d’être mis à jour.\n\n";
    }
    return '';
 }
 
}
