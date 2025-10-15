# Jeu vidéo API

Ce projet a été réalisé dans un cadre scolaire, avec pour objectif de découvrir la création d'APIs avec Symfony sans recourir à API Platform. 
Il s'agit d'une API RESTful développée from scratch pour mieux comprendre les mécanismes sous-jacents.

# Description

Cette API permet de gérer une base de données de jeux vidéo avec les fonctionnalités suivantes :

- Création et gestion de jeux vidéo
- Gestion des éditeurs
- Gestion des catégories
- Système d'utilisateurs
- Envoi automatique de newsletters pour les prochaines sorties

# Prérequis

PHP 8.0 +

Composer

MySQL / MariaDB

Symfony CLI

# Installation

### Cloner le projet

```
git clone https://github.com/GuerdaYacine/symfony-api.git
cd symfony-api
```

### Installer les dépendances

```
composer install
```

### Rendez-vous ensuite dans le .env et modifier les informations pour créer votre propre base de données.

```
DATABASE_URL="mysql://utilisateur:motdepasse@127.0.0.1:3306/nom_base_données?serverVersion=8.0.32&charset=utf8mb4"
```

### Une fois ceci fait, créez la base de données et remplissez la.

```
symfony console doctrine:database:create
symfony doctrine:migrations:migrate
symfony console doctrine:fixtures:load
```

### Démarrez le serveur

```
symfony serve -d
```

### Accedez à la doc : 

```
/api/v1/doc
```

### Executez la commande pour envoyer les prochains jeux à venir via le terminal

```
symfony console app:send-newsletter
```

### Ou executez le scheduler pour envoyer les prochains jeux tous les lundi à 8h30

```
symfony console messenger:consume
```
Puis selectionnez : 
scheduler_send_newsletter

# Problèmes

Si vous rencontrez le moindre problème n'hésitez pas à me contacter : guerda.yacine60100@gmail.com
