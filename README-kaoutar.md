# Entités complétées par [Imane Aitmessaoud]

## ✅ Terminé
- **User** (avec relations vers Conversation et Commande)
- **Conversation** (gestion clients inscrits/invités)  
- **Message** (avec SenderType enum et analytics)
- **pas Tous les Enums** (5 enums complets)

## 🔄 À compléter par Kaoutar Belail
- **MenuCategory** + **MenuItem** + **MenuPersonalization**
- **CommandeItem** (relation MenuItem ↔ CommandeItem)
- Compléter les relations dans **Commande**

## 📁 Structure
src/
├── Enum/ (5 enums - pas  TERMINÉ)
├── Entity/
│   ├── User.php (TERMINÉ)
│   ├── Conversation.php (TERMINÉ)
│   ├── Message.php (TERMINÉ)
│   ├── Commande.php (Relations commentées - À compléter)
│   └── [À créer par Kaoutar belail]
└── Repository/ (4 repositories - TERMINÉ)

## 🚀 Database
- Migrations appliquées
- Fixtures de test chargées
- Relations bidirectionnelles testées
