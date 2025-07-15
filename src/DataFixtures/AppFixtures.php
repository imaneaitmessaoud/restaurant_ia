<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\MenuCategory;
use App\Entity\MenuItem;
use App\Entity\MenuPersonalization;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Enum\RoleEnum;
use App\Enum\StatutUserEnum;
use App\Enum\SenderTypeEnum;
use App\Enum\StatutCommandeEnum;
use App\Enum\TypeServiceEnum;
use App\Enum\PersonalizationTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Utilisateurs
        $admin = new User();
        $admin->setNom('Admin Restaurant')->setEmail('admin@restaurant.ma')
              ->setTelephone('+212661111111')->setPassword('$2y$13$hashedpassword')
              ->setRole(RoleEnum::ADMIN);
        $manager->persist($admin);

        $client1 = new User();
        $client1->setNom('Ahmed Benali')->setEmail('ahmed@email.com')
                ->setTelephone('+212662222222')->setPassword('$2y$13$hashedpassword');
        $manager->persist($client1);

        // 2. Catégories de menu
        $pizzas = new MenuCategory();
        $pizzas->setNom('Pizzas')->setDescription('Nos délicieuses pizzas artisanales')
               ->setOrdre(1);
        $manager->persist($pizzas);

        $boissons = new MenuCategory();
        $boissons->setNom('Boissons')->setDescription('Boissons fraîches et chaudes')
                 ->setOrdre(2);
        $manager->persist($boissons);

        // 3. Articles de menu
        $margherita = new MenuItem();
        $margherita->setNom('Pizza Margherita')->setDescription('Tomate, mozzarella, basilic')
                   ->setPrixFloat(65.00)->setCategory($pizzas)
                   ->setIngredients(['tomate', 'mozzarella', 'basilic'])
                   ->setTempsPreparation(15);
        $manager->persist($margherita);

        $pepperoni = new MenuItem();
        $pepperoni->setNom('Pizza Pepperoni')->setDescription('Tomate, mozzarella, pepperoni')
                  ->setPrixFloat(75.00)->setCategory($pizzas)
                  ->setIngredients(['tomate', 'mozzarella', 'pepperoni'])
                  ->setTempsPreparation(18);
        $manager->persist($pepperoni);

        $coca = new MenuItem();
        $coca->setNom('Coca-Cola')->setDescription('Boisson gazeuse 33cl')
              ->setPrixFloat(15.00)->setCategory($boissons)
              ->setTempsPreparation(1);
        $manager->persist($coca);

        // 4. Personnalisations
        $taillePizza = new MenuPersonalization();
        $taillePizza->setMenuItem($margherita)->setType(PersonalizationTypeEnum::TAILLE)
                    ->setOptionsJson([
                        'individuelle' => 'Individuelle (26cm)',
                        'moyenne' => 'Moyenne (30cm)', 
                        'grande' => 'Grande (34cm)'
                    ])
                    ->setObligatoire(true)->setPrixSupplementFloat(0);
        $manager->persist($taillePizza);

        // 5. Conversations et Messages
        $conv1 = new Conversation();
        $conv1->setPhoneNumber('+212662222222')->setClientName('Ahmed Benali')
              ->setUser($client1);
        $manager->persist($conv1);

        $messages = [
            ['Bonjour, je voudrais commander une pizza', SenderTypeEnum::CLIENT],
            ['Bonjour Ahmed ! Quelle pizza souhaitez-vous ?', SenderTypeEnum::BOT],
            ['Une margherita grande s\'il vous plaît', SenderTypeEnum::CLIENT],
        ];

        foreach ($messages as $index => $messageData) {
            $message = new Message();
            $message->setContenu($messageData[0])->setSenderType($messageData[1])
                    ->setConversation($conv1);
            $timestamp = (new \DateTimeImmutable())->modify('-' . (count($messages) - $index) * 5 . ' minutes');
            $message->setTimestamp($timestamp);
            $manager->persist($message);
        }

        // 6. Commandes
        $commande1 = new Commande();
        $commande1->setUser($client1)->setTypeService(TypeServiceEnum::LIVRAISON)
                  ->setAdresseLivraison('123 Rue Mohammed V, Casablanca')
                  ->setStatut(StatutCommandeEnum::EN_PREPARATION);
        $manager->persist($commande1);

        // 7. Items de commande
        $item1 = new CommandeItem();
        $item1->setMenuItem($margherita)->setCommande($commande1)
              ->setQuantite(1)->setPrixUnitaireFloat(90.00)
              ->setPersonalisationJson(['taille' => 'grande']);
        $manager->persist($item1);

        $item2 = new CommandeItem();
        $item2->setMenuItem($coca)->setCommande($commande1)
              ->setQuantite(2)->setPrixUnitaireFloat(15.00);
        $manager->persist($item2);

        // Calculer le total
        $commande1->setTotalFloat(90.00 + (15.00 * 2) + 15.00); // +15 DH livraison

        $manager->flush();
    }
}