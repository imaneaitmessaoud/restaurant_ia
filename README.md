🍽️ Gestion des commandes Restaurant avec IA
Bienvenue dans le projet Gestion des commandes Restaurant avec IA ! 🎉 Ce projet est une application complète pour gérer les commandes dans un restaurant avec une application mobile Flutter, un backend Symfony, et une base de données MySQL. L’IA est intégrée pour optimiser la gestion et l’expérience client.

📂 Structure du projet
backend/ 🖥️ : Backend Symfony avec API Platform, gestion des commandes, produits, utilisateurs et base de données MySQL.

frontend/ 📱 : Application mobile Flutter pour passer des commandes et interagir avec le restaurant.

.github/workflows/ 🚀 : Configuration CI/CD pour automatiser tests et déploiement.

README.md 📜 : Ce fichier, qui contient toutes les instructions nécessaires.

🛠️ Prérequis
Avant de commencer, assurez-vous d’avoir installé :

PHP 8.2 ☕ (XAMPP recommandé)

Composer 2.8.9+ 🧩

Symfony CLI (optionnel)

MySQL 8.0+ 🗄️

Flutter SDK et Flutter CLI

Git 🐙

⚙️ Installation et lancement du backend Symfony
1️⃣ Cloner le dépôt

bash
Copier
Modifier
git clone https://github.com/imaneaitmessaoud/restaurant_ia.git
cd backend
2️⃣ Installer les dépendances Composer

bash
Copier
Modifier
composer install
3️⃣ Installer les bundles nécessaires

bash
Copier
Modifier
composer require api-platform/core
composer require doctrine/orm doctrine/doctrine-bundle
composer require --dev symfony/maker-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
composer require nelmio/cors-bundle
4️⃣ Configurer la base de données

Modifier .env ou .env.local pour indiquer vos identifiants MySQL, par exemple :

ini
Copier
Modifier
DATABASE_URL="mysql://root:motdepasse@127.0.0.1:3306/restaurant_ia"
5️⃣ Créer la base de données

bash
Copier
Modifier
php bin/console doctrine:database:create
6️⃣ Exécuter les migrations (si existantes)

bash
Copier
Modifier
php bin/console doctrine:migrations:migrate
7️⃣ Lancer le serveur Symfony

bash
Copier
Modifier
php -S 127.0.0.1:8000 -t public
Le backend sera accessible sur : http://127.0.0.1:8000

🚀 Lancement du frontend Flutter
1️⃣ Aller dans le dossier frontend Flutter :

bash
Copier
Modifier
cd ../frontend
2️⃣ Installer les dépendances :

bash
Copier
Modifier
flutter pub get
3️⃣ Lancer l’application :

bash
Copier
Modifier
flutter run
🔍 Fonctionnalités principales
Gestion complète des commandes et produits

Interface mobile intuitive et responsive

API REST sécurisée et documentée via API Platform

Intelligence artificielle pour recommandations et optimisation des commandes

Authentification des clients

Notifications en temps réel

📜 Licence
Ce projet est sous licence MIT. Vous êtes libres de l’utiliser et de le modifier selon vos besoins.

🤝 Contribution
Les contributions sont les bienvenues !

Forkez le projet

Créez une branche feature/nom-fonctionnalité

Faites vos modifications et commitez

Poussez sur votre fork

Ouvrez une Pull Request

📞 Contact
Pour toute question ou suggestion, merci d’ouvrir une issue ou de nous contacter via GitHub.
