
# 🍽️ Gestion des commandes Restaurant avec IA

Bienvenue dans le projet **Gestion des commandes Restaurant avec IA** ! 🎉  
Ce projet est une application complète pour gérer les commandes dans un restaurant avec une application mobile Flutter, un backend Symfony, et une base de données MySQL. L’IA est intégrée pour optimiser la gestion et l’expérience client.

---

## 📂 Structure du projet

- **backend/** 🖥️ : Backend Symfony avec API Platform, gestion des commandes, produits, utilisateurs et base de données MySQL.  
- **frontend/** 📱 : Application mobile Flutter pour passer des commandes et interagir avec le restaurant.  
- **.github/workflows/** 🚀 : Configuration CI/CD pour automatiser tests et déploiement.  
- **README.md** 📜 : Ce fichier, qui contient toutes les instructions nécessaires.

---

## 🛠️ Prérequis

Avant de commencer, assurez-vous d’avoir installé :

- PHP 8.2 ☕ (XAMPP recommandé)  
- Composer 2.8.9+ 🧩  
- Symfony CLI (optionnel)  
- MySQL 8.0+ 🗄️  
- Flutter SDK et Flutter CLI  
- Git 🐙

---

## ⚙️ Installation et lancement du backend Symfony

### 1️⃣ Cloner le dépôt  
```bash
git clone https://github.com/imaneaitmessaoud/restaurant_ia.git
cd backend
```

### 2️⃣ Installer les dépendances Composer  
```bash
composer install
```

### 3️⃣ Installer les bundles nécessaires  
```bash
composer require api-platform/core
composer require doctrine/orm doctrine/doctrine-bundle
composer require --dev symfony/maker-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
composer require nelmio/cors-bundle
```

### 4️⃣ Configurer la base de données

Modifier le fichier `.env` ou `.env.local` avec vos identifiants MySQL :  
```ini
DATABASE_URL="mysql://root:motdepasse@127.0.0.1:3306/restaurant_ia"
```

### 5️⃣ Créer la base de données  
```bash
php bin/console doctrine:database:create
```

### 6️⃣ Exécuter les migrations (si existantes)  
```bash
php bin/console doctrine:migrations:migrate
```

### 7️⃣ Lancer le serveur Symfony  
```bash
php -S 127.0.0.1:8000 -t public
```

➡️ Le backend sera accessible sur : `http://127.0.0.1:8000`

---

## 🚀 Lancement du frontend Flutter

### 1️⃣ Aller dans le dossier Flutter  
```bash
cd ../frontend
```

### 2️⃣ Installer les dépendances  
```bash
flutter pub get
```

### 3️⃣ Lancer l’application  
```bash
flutter run
```

---

## 🔍 Fonctionnalités principales

- ✅ Gestion complète des commandes et des produits  
- ✅ Interface mobile intuitive et responsive  
- ✅ API REST sécurisée (API Platform)  
- ✅ IA intégrée pour optimisation des commandes  
- ✅ Authentification des clients  
- ✅ Notifications en temps réel

---

## 📜 Licence

Ce projet est sous licence **MIT**. Vous êtes libres de l’utiliser et de le modifier selon vos besoins. 🎉

---

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Forkez le projet 🍴  
2. Créez une branche :  
```bash
git checkout -b feature/ma-fonctionnalité
```
3. Commitez vos modifications :  
```bash
git commit -m "Ajout : ma nouvelle fonctionnalité"
```
4. Poussez sur votre fork :  
```bash
git push origin feature/ma-fonctionnalité
```
5. Ouvrez une **Pull Request** 📬

---

## 📞 Contact

Pour toute question ou suggestion, merci d’ouvrir une issue sur GitHub ou de nous contacter via l'onglet Discussions. 💬

---

