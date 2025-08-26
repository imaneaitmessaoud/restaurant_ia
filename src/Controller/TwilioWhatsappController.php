<?php
// src/Controller/TwilioWhatsappController.php
namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use App\Service\MenuExcelReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class TwilioWhatsappController extends AbstractController
{
    public function __construct(
        private MenuExcelReader $reader,
        private CacheInterface $cache,
        private EntityManagerInterface $em
    ) {}

    #[Route('/twilio/whatsapp', name: 'twilio_whatsapp', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $from = (string)$request->request->get('From', 'unknown'); // ex: whatsapp:+2126...
        $text = trim((string)$request->request->get('Body', ''));

        $msg  = $this->handleMessage($from, $text);

        $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Message>{$msg}</Message>
</Response>
XML;
        return new Response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function handleMessage(string $from, string $text): string
    {
        if ($text === '') {
            return "Bonjour 👋 Tapez :\n- menu\n- prix <plat>\n- commander <plat> x<qte>\n- valider Nom Prenom, Téléphone, Adresse\nExemples : prix Margherita / commander Margherita x2";
        }

        $t = mb_strtolower($text);

        // MENU
        if ($t === 'menu') {
            return $this->replyMenu();
        }

        // PRIX <plat>
        if (preg_match('/^prix\s+(.+)$/ui', $text, $m)) {
            $plat = trim($m[1]);
            return $this->replyPrice($plat);
        }

        // COMMANDER <plat> x<qte>
        if (preg_match('/^commander\s+(.+?)\s*x\s*(\d+)$/ui', $text, $m)) {
            $plat = trim($m[1]);
            $qte  = (int)$m[2];
            return $this->replyOrder($from, $plat, $qte);
        }

        // VALIDER Nom, Téléphone, Adresse  (virgules obligatoires dans cet exemple)
        if (preg_match('/^valider\s+(.+?),\s*([\+\d\s\-]+),\s*(.+)$/ui', $text, $m)) {
            $nomComplet = trim($m[1]);
            $telephone  = preg_replace('/\D+/', '', $m[2]); // on garde que les chiffres
            $adresse    = trim($m[3]);
            return $this->confirmOrder($from, $nomComplet, $telephone, $adresse);
        }

        return "Je n’ai pas compris 🤔\nEssayez :\n- menu\n- prix Margherita\n- commander Margherita x2\n- valider Nom Prenom, Téléphone, Adresse";
    }

    private function replyMenu(): string
    {
        $items = $this->reader->readMenu();
        if (!$items) return "Désolé, le menu est indisponible pour le moment.";

        $out = "📋 Voici notre menu :\n\n";
        foreach ($items as $i) {
            $out .= "🍽 {$i['name']} — {$i['price']} MAD\n";
        }
        return $out;
    }

    private function replyPrice(string $plat): string
    {
        $item = $this->findItem($plat);
        if (!$item) {
            return "Je n’ai pas trouvé “{$plat}”. Tapez *menu* pour voir la liste.";
        }
        return "Le *{$item['name']}* coûte *{$item['price']} MAD*.\nSouhaitez-vous le commander ? (ex: commander {$item['name']} x1)";
    }

    private function replyOrder(string $from, string $plat, int $qte): string
    {
        if ($qte < 1) $qte = 1;
        $item = $this->findItem($plat);
        if (!$item) {
            return "Je n’ai pas trouvé “{$plat}”. Tapez *menu* pour voir la liste.";
        }
        $total = (float)$item['price'] * $qte;
        $total = number_format($total, 2, '.', '');

        // mémoriser la commande en cours pendant 30 minutes
        $key = 'pending_order_'.md5($from);
        $this->cache->delete($key);
        $this->cache->get($key, function() use ($item, $qte, $total) {
            return [
                'name'       => $item['name'],
                'unit_price' => (float)$item['price'],
                'qty'        => $qte,
                'total'      => (float)$total,
            ];
        });

        return "✅ Commande récap :\n- {$item['name']} x{$qte}\n- Total : {$total} MAD\n\nPour valider, répondez avec :\nvalider Nom Prenom, Téléphone, Adresse";
    }

    private function confirmOrder(string $from, string $nomComplet, string $telephone, string $adresse): string
    {
        $key = 'pending_order_'.md5($from);
        $pending = $this->cache->get($key, fn() => null);
        if (!$pending) {
            return "Aucune commande en cours à valider. Tapez *menu* ou *commander <plat> x<qte>*.";
        }

        // Trouver ou créer l'utilisateur par téléphone
        $userRepo = $this->em->getRepository(User::class);
        $user = $userRepo->findOneBy(['telephone' => $telephone]);
        if (!$user) {
            $user = new User();
            $user->setNom($nomComplet);
            $user->setEmail($telephone.'@example.local'); // placeholder si pas d’email réel
            $user->setTelephone($telephone);
            $user->setPassword('N/A'); // si pas d’auth
            $this->em->persist($user);
        }

        // Créer la commande
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setTotalFloat($pending['total']);
        $commande->setStatut(StatutCommandeEnum::CONFIRMEE);
        $commande->setTypeService($adresse ? TypeServiceEnum::LIVRAISON : TypeServiceEnum::SUR_PLACE);
        $commande->setAdresseLivraison($adresse ?: null);
        $commande->setCommentaire("WhatsApp: {$pending['name']} x{$pending['qty']} @{$pending['unit_price']} MAD");

        $this->em->persist($commande);
        $this->em->flush();

        // Clear pending
        $this->cache->delete($key);

        return "🎉 Commande validée !\n- Ref: {$commande->getReference()}\n- Client: {$user->getDisplayName()}\n- Total: {$commande->getFormattedTotal()}\n- Livraison: ".($adresse ? "OUI" : "NON")."\nMerci 🙏";
    }

    private function findItem(string $needle): ?array
    {
        $items = $this->reader->readMenu();
        $needleLower = mb_strtolower($needle);

        foreach ($items as $i) {
            if (mb_strtolower($i['name']) === $needleLower) {
                return $i; // match exact
            }
        }
        foreach ($items as $i) {
            if (str_contains(mb_strtolower($i['name']), $needleLower)) {
                return $i;
            }
        }
        return null;
    }
}
