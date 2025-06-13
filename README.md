# 🦊 Foxy - Gestion de Crèche

**Foxy** est une application web moderne de gestion de crèche développée avec Symfony 7.2. Elle permet aux équipes éducatives et aux parents de gérer efficacement les activités quotidiennes d'une crèche.

## 📋 Fonctionnalités

### 👥 Gestion des Familles
- **Inscription des enfants** avec informations complètes (allergies, conditions de santé, etc.)
- **Gestion des parents** avec profils détaillés
- **Attribution automatique des équipes** selon l'âge des enfants
- **Photos des enfants** avec upload sécurisé

### 📅 Planning et Présence
- **Planning hebdomadaire** interactif (7h-19h)
- **Gestion des présences** par jour
- **Système de quarts** pour les éducateurs
- **Vue par enfant** et **vue par équipe**
- **Ajout/suppression** rapide d'enfants pour la semaine

### 💬 Messagerie
- **Système de conversations** entre parents et équipe
- **Notifications en temps réel** des nouveaux messages
- **Historique des échanges** complet

### 📄 Gestion Documentaire
- **Documents généraux** de la crèche
- **Documents familiaux** par enfant
- **Upload sécurisé** (PDF, Word, images)
- **Accès différencié** selon les rôles

### 👨‍💼 Gestion des Équipes
- **Profils éducateurs** avec photos
- **Planning des équipes** et rotations
- **Gestion des activités** par équipe

## 🛠️ Technologies Utilisées

### Backend
- **Symfony 7.2** - Framework PHP
- **Doctrine ORM** - Gestion de la base de données
- **PostgreSQL** - Base de données
- **Symfony Security** - Authentification et autorisation

### Frontend
- **Twig** - Moteur de templates
- **Stimulus.js** - Contrôleurs JavaScript
- **Turbo** - Navigation fluide
- **Sass** - Préprocesseur CSS
- **Webpack Encore** - Build des assets

### Outils de Développement
- **Composer** - Gestion des dépendances PHP
- **npm** - Gestion des dépendances JavaScript

## 🚀 Installation

### Prérequis
- PHP 8.2 ou supérieur
- Composer
- Node.js et npm
- Docker et Docker Compose
- PostgreSQL

### Étapes d'installation

1. **Cloner le repository**
```bash
git clone https://github.com/votre-username/foxy.git
cd foxy
```

2. **Installer les dépendances PHP**
```bash
composer install
```

3. **Installer les dépendances JavaScript**
```bash
npm install
```

4. **Configurer l'environnement**
```bash
cp .env .env.local
# Éditer .env.local avec vos paramètres de base de données
```

5. **Démarrer les services Docker**
```bash
docker-compose up -d
```

6. **Créer la base de données**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

7. **Créer un administrateur**
```bash
php bin/console app:create-admin
```

8. **Compiler les assets**
```bash
npm run build
```

9. **Démarrer le serveur de développement**
```bash
symfony server:start
```

## 👤 Rôles Utilisateurs

### ROLE_ADMIN
- Accès complet à toutes les fonctionnalités
- Gestion des utilisateurs et des équipes
- Upload de documents généraux
- Création et modification d'enfants

### ROLE_EDUCATOR
- Accès au planning et aux présences
- Gestion des activités de son équipe
- Messagerie avec les parents
- Consultation des documents

### ROLE_PARENT
- Consultation des informations de ses enfants
- Accès aux documents familiaux
- Messagerie avec l'équipe
- Consultation du planning de ses enfants

## 📁 Structure du Projet

```
foxy/
├── src/
│   ├── Controller/          # Contrôleurs Symfony
│   ├── Entity/             # Entités Doctrine
│   ├── Repository/         # Repositories Doctrine
│   ├── Form/              # Formulaires Symfony
│   └── Command/           # Commandes console
├── templates/             # Templates Twig
├── assets/               # Assets frontend (JS, CSS, images)
├── public/               # Fichiers publics
├── migrations/           # Migrations Doctrine
├── config/              # Configuration Symfony
└── tests/               # Tests unitaires
```

## 🔧 Configuration

### Variables d'environnement importantes
```env
# Base de données
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"

# Upload des fichiers
CHILDREN_PICTURES_DIRECTORY="%kernel.project_dir%/public/uploads/children"
DOCUMENTS_DIRECTORY="%kernel.project_dir%/public/uploads/documents"
```

## 📝 Utilisation

### Création d'un nouvel enfant
1. Se connecter en tant qu'admin
2. Aller dans "Famille" → "Nouveau"
3. Remplir les informations de l'enfant et des parents
4. L'équipe sera attribuée automatiquement selon l'âge

### Gestion du planning
1. Aller dans "Planning"
2. Sélectionner la semaine souhaitée
3. Ajouter les enfants pour la semaine
4. Définir les heures d'arrivée et de départ

### Messagerie
1. Cliquer sur "Message" dans le menu principal
2. Sélectionner le destinataire
3. Écrire et envoyer le message
4. Les notifications apparaissent en temps réel

## 🧪 Tests

```bash
# Lancer les tests unitaires
php bin/phpunit

# Lancer les tests avec couverture
php bin/phpunit --coverage-html coverage/
```

## 🚀 Déploiement

### Production
1. Configurer les variables d'environnement de production
2. Compiler les assets : `npm run build`
3. Vider le cache : `php bin/console cache:clear --env=prod`
4. Exécuter les migrations : `php bin/console doctrine:migrations:migrate --env=prod`

### Docker
```bash
# Build de l'image
docker build -t foxy .

# Démarrage des services
docker-compose -f docker-compose.prod.yml up -d
```

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.

## 👥 Équipe

- **Développeur** - Mel00w


**Foxy** - Simplifiez la gestion de votre crèche ! 🦊✨
