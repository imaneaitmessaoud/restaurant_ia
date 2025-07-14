<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\RoleEnum;
use App\Enum\StatutUserEnum;
use App\Enum\SenderTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Créer des utilisateurs
        $admin = new User();
        $admin->setNom('Admin Restaurant');
        $admin->setEmail('admin@restaurant.ma');
        $admin->setTelephone('+212661111111');
        $admin->setPassword('$2y$13$hashedpassword'); // À hasher en production
        $admin->setRole(RoleEnum::ADMIN);
        $manager->persist($admin);

        $client1 = new User();
        $client1->setNom('Ahmed Benali');
        $client1->setEmail('ahmed@email.com');
        $client1->setTelephone('+212662222222');
        $client1->setPassword('$2y$13$hashedpassword');
        $client1->setRole(RoleEnum::CLIENT);
        $manager->persist($client1);

        $client2 = new User();
        $client2->setNom('Fatima Zahra');
        $client2->setEmail('fatima@email.com');
        $client2->setTelephone('+212663333333');
        $client2->setPassword('$2y$13$hashedpassword');
        $client2->setRole(RoleEnum::CLIENT);
        $manager->persist($client2);

        // 2. Créer des conversations
        // Conversation 1: Client inscrit
        $conv1 = new Conversation();
        $conv1->setPhoneNumber('+212662222222');
        $conv1->setClientName('Ahmed Benali');
        $conv1->setUser($client1);
        $conv1->setContext('Commande pizza');
        $manager->persist($conv1);

        // Conversation 2: Client invité
        $conv2 = new Conversation();
        $conv2->setPhoneNumber('+212664444444');
        $conv2->setClientName('Omar Guest');
        // Pas d'utilisateur (client invité)
        $manager->persist($conv2);

        // 3. Créer des messages pour conv1
        $messages1 = [
            ['Bonjour, je voudrais commander', SenderTypeEnum::CLIENT],
            ['Bonjour Ahmed ! Je peux vous aider avec votre commande', SenderTypeEnum::BOT],
            ['Je voudrais une pizza margherita grande', SenderTypeEnum::CLIENT],
            ['Excellente choix ! Cela fera 90 DH. Confirmez-vous ?', SenderTypeEnum::BOT],
            ['Oui, je confirme', SenderTypeEnum::CLIENT],
            ['Parfait ! Votre commande est en préparation', SenderTypeEnum::HUMAIN],
        ];

        foreach ($messages1 as $index => $messageData) {
            $message = new Message();
            $message->setContenu($messageData[0]);
            $message->setSenderType($messageData[1]);
            $message->setConversation($conv1);
            
            // Échelonner les timestamps (5 min d'écart)
            $timestamp = (new \DateTimeImmutable())->modify('-' . (count($messages1) - $index) * 5 . ' minutes');
            $message->setTimestamp($timestamp);
            
            if ($index < 4) { // Marquer les 4 premiers comme lus
                $message->markAsRead();
            }
            
            $manager->persist($message);
        }

        // 4. Créer des messages pour conv2
        $messages2 = [
            ['Salut', SenderTypeEnum::CLIENT],
            ['Bonjour ! Comment puis-je vous aider ?', SenderTypeEnum::BOT],
            ['Vous avez quoi comme menu ?', SenderTypeEnum::CLIENT],
            ['Voici notre menu : pizzas, burgers, salades...', SenderTypeEnum::BOT],
        ];

        foreach ($messages2 as $index => $messageData) {
            $message = new Message();
            $message->setContenu($messageData[0]);
            $message->setSenderType($messageData[1]);
            $message->setConversation($conv2);
            
            $timestamp = (new \DateTimeImmutable())->modify('-' . (count($messages2) - $index) * 3 . ' minutes');
            $message->setTimestamp($timestamp);
            
            $manager->persist($message);
        }

        // 5. Sauvegarder tout
        $manager->flush();
    }
}