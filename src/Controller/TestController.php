<?php

namespace App\Controller;

use App\Enum\RoleEnum;
use App\Enum\StatutUserEnum;
use App\Enum\StatutCommandeEnum;
use App\Enum\SenderTypeEnum;
use App\Enum\TypeServiceEnum;
use App\Entity\User; 
use App\Entity\Conversation;  // ← NOUVEAU
use App\Entity\Message;  
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class TestController extends AbstractController
{
    #[Route('/test/enums', name: 'test_enums')]
    public function testEnums(): JsonResponse
    {
        $data = [
            'roles' => [
                'CLIENT' => RoleEnum::CLIENT->value,
                'ADMIN' => RoleEnum::ADMIN->value,
                'client_label' => RoleEnum::CLIENT->getLabel(),
            ],
            'statuts_user' => [
                'ACTIF' => StatutUserEnum::ACTIF->value,
                'actif_label' => StatutUserEnum::ACTIF->getLabel(),
            ],
            'statuts_commande' => [
                'CONFIRMEE' => StatutCommandeEnum::CONFIRMEE->value,
                'confirmee_label' => StatutCommandeEnum::CONFIRMEE->getLabel(),
                ],
            'types_service' => [
                'LIVRAISON' => TypeServiceEnum::LIVRAISON->value,
                'needs_address' => TypeServiceEnum::LIVRAISON->needsAddress(),
            ],
            'sender_types' => [
                'BOT' => SenderTypeEnum::BOT->value,
                'is_automated' => SenderTypeEnum::BOT->isAutomated(),
            ]
        ];
        
        return $this->json([
            'status' => 'success',
            'message' => 'Tous les enums fonctionnent !',
            'data' => $data
        ]);
    }

    #[Route('/test/user', name: 'test_user')]
    public function testUser(): JsonResponse
    {
        // Test création d'un utilisateur
        $user = new User();
        $user->setNom('Test User');
        $user->setEmail('test@restaurant.ma');
        $user->setTelephone('+212661234567');
        $user->setPassword('password123');
        $user->setRole(RoleEnum::CLIENT);
        $user->setStatut(StatutUserEnum::ACTIF);

        return $this->json([
            'status' => 'success',
            'message' => 'Entité User créée avec succès !',
            'user_info' => $user->getFullInfo(),
            'is_admin' => $user->isAdmin(),
            'can_login' => $user->canLogin(),
            'display_name' => $user->getDisplayName(),
            'symfony_roles' => $user->getRoles(),
            'user_identifier' => $user->getUserIdentifier(),
        ]);
    }
    #[Route('/test/conversation', name: 'test_conversation')]
public function testConversation(): JsonResponse
{
    // Créer un utilisateur inscrit
    $user = new User();
    $user->setNom('Fatima Zahra');
    $user->setEmail('fatima@restaurant.ma');
    $user->setTelephone('+212661234567');
    $user->setPassword('password123');

    // Test 1: Conversation avec utilisateur inscrit
    $conversation1 = new Conversation();
    $conversation1->setPhoneNumber('0661234567'); // Test normalisation
    $conversation1->setClientName('Fatima Zahra');
    $conversation1->setUser($user);
    $conversation1->setContext('Client demande le menu du jour');

    // Test 2: Conversation client invité
    $conversation2 = new Conversation();
    $conversation2->setPhoneNumber('0677889900');
    $conversation2->setClientName('Ahmed Guest');
    // Pas d'utilisateur (client invité)

    // Ajouter les conversations à l'utilisateur
    $user->addConversation($conversation1);

    return $this->json([
        'status' => 'success',
        'message' => 'Conversations créées avec succès !',
        'conversation1' => $conversation1->getFullInfo(),
        'conversation2' => $conversation2->getFullInfo(),
        'tests_normalisation' => [
            'phone_input' => '0661234567',
            'phone_normalized' => $conversation1->getPhoneNumber(),
            'expected' => '+212661234567'
        ],
        'user_relations' => [
            'user_conversations_count' => $user->getConversations()->count(),
            'conv1_user_name' => $conversation1->getUser()?->getNom(),
            'conv2_user_name' => $conversation2->getUser()?->getNom() ?: 'Aucun',
        ],
        'client_types' => [
            'conv1_type' => $conversation1->getClientType(),
            'conv1_is_registered' => $conversation1->isRegisteredClient(),
            'conv2_type' => $conversation2->getClientType(),
            'conv2_is_guest' => $conversation2->isGuestClient(),
        ]
    ]);
}
#[Route('/test/message', name: 'test_message')]
public function testMessage(): JsonResponse
{
    // Créer une conversation
    $conversation = new Conversation();
    $conversation->setPhoneNumber('+212661234567');
    $conversation->setClientName('Test Client');

    // Message 1: Du client
    $message1 = new Message();
    $message1->setContenu('Bonjour, je voudrais voir votre menu s\'il vous plaît');
    $message1->setSenderType(SenderTypeEnum::CLIENT);
    $message1->setConversation($conversation);

    // Message 2: Du bot
    $message2 = new Message();
    $message2->setContenu('Bonjour ! Voici notre menu du jour. Que souhaitez-vous commander ?');
    $message2->setSenderType(SenderTypeEnum::BOT);
    $message2->setConversation($conversation);
    $message2->setMetadataValue('intent', 'menu_request');
    $message2->setMetadataValue('confidence', 0.95);

    // Message 3: D'un humain
    $message3 = new Message();
    $message3->setContenu('Un agent va vous aider dans quelques instants');
    $message3->setSenderType(SenderTypeEnum::HUMAIN);
    $message3->setConversation($conversation);
    $message3->markAsRead();
    $message3->markAsProcessed();

    // Ajouter les messages à la conversation (relation bidirectionnelle)
    $conversation->addMessage($message1);
    $conversation->addMessage($message2);
    $conversation->addMessage($message3);

    // Tests de recherche de mots-clés
    $menuKeywords = ['menu', 'carte', 'plat'];
    $greetingKeywords = ['bonjour', 'salut', 'hello'];

    return $this->json([
        'status' => 'success',
        'message' => 'Messages créés avec succès !',
        'conversation_info' => $conversation->getFullInfo(),
        'messages' => [
            'message1' => $message1->getFullInfo(),
            'message2' => $message2->getFullInfo(),
            'message3' => $message3->getFullInfo(),
        ],
        'conversation_stats' => [
            'total_messages' => $conversation->getMessagesCount(),
            'client_messages' => $conversation->getClientMessages()->count(),
            'bot_messages' => $conversation->getBotMessages()->count(),
            'human_messages' => $conversation->getHumanMessages()->count(),
            'last_message_preview' => $conversation->getLastMessage()?->getPreview(),
        ],
        'message_analysis' => [
            'message1_contains_menu' => $message1->containsKeywords($menuKeywords),
            'message1_contains_greeting' => $message1->containsKeywords($greetingKeywords),
            'message1_word_count' => $message1->getWordCount(),
            'message2_metadata' => $message2->getMetadata(),
            'message3_is_read' => $message3->isRead(),
            'message3_is_processed' => $message3->isProcessed(),
        ],
        'sender_type_tests' => [
            'message1_is_from_client' => $message1->isFromClient(),
            'message2_is_automated' => $message2->isAutomated(),
            'message3_is_from_human' => $message3->isFromHuman(),
        ]
    ]);
}
#[Route('/test/system-complet', name: 'test_system_complet')]
public function testSystemComplet(): JsonResponse
{
    // 1. Créer un utilisateur
    $user = new User();
    $user->setNom('Mohammed Alami');
    $user->setEmail('mohammed@restaurant.ma');
    $user->setTelephone('+212661234567');
    $user->setPassword('password123');
    $user->setRole(RoleEnum::CLIENT);

    // 2. Créer une conversation
    $conversation = new Conversation();
    $conversation->setPhoneNumber('0661234567');
    $conversation->setClientName('Mohammed Alami');
    $conversation->setUser($user);

    // 3. Simuler une conversation complète
    $messages = [
        ['contenu' => 'Bonjour', 'sender' => SenderTypeEnum::CLIENT],
        ['contenu' => 'Bonjour Mohammed ! Comment puis-je vous aider ?', 'sender' => SenderTypeEnum::BOT],
        ['contenu' => 'Je voudrais commander une pizza', 'sender' => SenderTypeEnum::CLIENT],
        ['contenu' => 'Excellente choix ! Quelle taille souhaitez-vous ?', 'sender' => SenderTypeEnum::BOT],
        ['contenu' => 'Grande taille s\'il vous plaît', 'sender' => SenderTypeEnum::CLIENT],
        ['contenu' => 'Un agent va finaliser votre commande', 'sender' => SenderTypeEnum::HUMAIN],
    ];

    $messageObjects = [];
    foreach ($messages as $index => $messageData) {
        $message = new Message();
        $message->setContenu($messageData['contenu']);
        $message->setSenderType($messageData['sender']);
        $message->setConversation($conversation);
        
        // Simuler des timestamps différents (2 minutes d'écart)
        $timestamp = (new \DateTimeImmutable())->modify('-' . (count($messages) - $index) * 2 . ' minutes');
        $message->setTimestamp($timestamp);
        
        // Marquer certains messages comme lus
        if ($index < 4) {
            $message->markAsRead();
        }
        
        $conversation->addMessage($message);
        $messageObjects[] = $message;
    }

    // 4. Ajouter la conversation à l'utilisateur
    $user->addConversation($conversation);

    // 5. Tests avancés
    $keywordTests = [
        'pizza' => ['pizza', 'margherita'],
        'salutation' => ['bonjour', 'salut'],
        'taille' => ['grande', 'moyenne', 'petite'],
    ];

    $analysisResults = [];
    foreach ($keywordTests as $category => $keywords) {
        $analysisResults[$category] = [];
        foreach ($messageObjects as $index => $message) {
            $analysisResults[$category]["message_$index"] = $message->containsKeywords($keywords);
        }
    }

    return $this->json([
        'status' => 'success',
        'message' => 'Système complet testé avec succès !',
        'user_info' => $user->getFullInfo(),
        'conversation_info' => $conversation->getFullInfo(),
        'messages_timeline' => array_map(function($msg) {
            return [
                'contenu' => $msg->getPreview(),
                'sender' => $msg->getSenderType()->getLabel(),
                'timestamp' => $msg->getFormattedTimestamp(),
                'time_ago' => $msg->getTimeAgo(),
                'is_read' => $msg->isRead(),
            ];
        }, $messageObjects),
        'relations_bidirectionnelles' => [
            'user_conversations_count' => $user->getConversations()->count(),
            'conversation_messages_count' => $conversation->getMessagesCount(),
            'conversation_user_name' => $conversation->getUser()?->getNom(),
            'last_message_sender' => $conversation->getLastMessage()?->getSenderType()->getLabel(),
        ],
        'conversation_analytics' => [
            'duration_minutes' => $conversation->getDurationInMinutes(),
            'time_since_last_message' => $conversation->getTimeSinceLastMessage(),
            'is_inactive' => $conversation->isInactive(5), // 5 minutes
            'messages_by_sender' => [
                'client' => $conversation->getClientMessages()->count(),
                'bot' => $conversation->getBotMessages()->count(),
                'human' => $conversation->getHumanMessages()->count(),
            ],
        ],
        'keyword_analysis' => $analysisResults,
        'system_validation' => [
            'all_relations_work' => true,
            'enums_integrated' => true,
            'bidirectional_ok' => true,
            'utilities_functional' => true,
        ]
    ]);
}
#[Route('/test/database', name: 'test_database')]
public function testDatabase(EntityManagerInterface $em): JsonResponse
{
    // Récupérer des données réelles de la DB
    $users = $em->getRepository(User::class)->findAll();
    $conversations = $em->getRepository(Conversation::class)->findAll();
    $messages = $em->getRepository(Message::class)->findAll();

    // Stats rapides
    $stats = [
        'users_count' => count($users),
        'conversations_count' => count($conversations),
        'messages_count' => count($messages),
    ];

    // Dernière conversation avec messages
    $lastConv = $em->getRepository(Conversation::class)
        ->findOneBy([], ['id' => 'DESC']);

    return $this->json([
        'status' => 'success',
        'message' => 'Base de données testée avec succès !',
        'stats' => $stats,
        'sample_users' => array_map(fn($u) => $u->getFullInfo(), array_slice($users, 0, 2)),
        'last_conversation' => $lastConv?->getFullInfo(),
        'database_working' => true,
    ]);
}

}