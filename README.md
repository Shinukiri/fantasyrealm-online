
# FantasyRealm Online

Application web de gestion de personnages dans un univers MMORPG fictif, développée dans le cadre de l'ECF du titre professionnel Développeur Web et Web Mobile (Studi).

**Projet :** FantasyRealm Online  
**Studio fictif :** PixelVerse Studios

## Liens du projet

- Application : https://fantasyrealm-online.alwaysdata.net/
- Code source : https://github.com/Shinukiri/fantasyrealm-online
- Gestion de projet : https://github.com/users/Shinukiri/projects/1

## Fonctionnalités

### Visiteur
- Consulter les pages publiques.
- Créer un compte et se connecter.
- Demander une réinitialisation du mot de passe.

### Utilisateur connecté
- Créer et consulter ses personnages.
- Dupliquer et supprimer ses personnages.
- Soumettre ses personnages à la modération.
- Utiliser les fonctions de partage et d'avis selon les conditions prévues par l'application.

### Employé
- Modérer les personnages et les avis.
- Accéder aux fonctions de gestion autorisées par son rôle.

### Administrateur
- Accéder aux fonctions de modération.
- Gérer les utilisateurs et les employés.
- Gérer les accessoires.

## Technologies

- Front-end : HTML5, CSS3.
- Back-end : PHP 8, PDO.
- Base relationnelle : MySQL / MariaDB.
- Journalisation prévue : MongoDB.
- Emails transactionnels : PHPMailer avec SMTP Brevo.
- Dépendances : Composer.
- Versionnement et suivi : Git, GitHub, GitHub Projects.
- Développement local : XAMPP et Visual Studio Code.
- Hébergement : Alwaysdata.

## Installation locale

### Prérequis
- PHP 8 compatible avec les dépendances du projet.
- MySQL ou MariaDB.
- Composer.
- Extension PHP MongoDB et serveur MongoDB pour la journalisation.
- Un serveur web compatible PHP, par exemple Apache avec XAMPP.

### Installation

1. Cloner le dépôt :

   ```bash
   git clone https://github.com/Shinukiri/fantasyrealm-online.git
   ```

2. Placer le projet dans le répertoire web local (par exemple `C:/xampp/htdocs/ecf_studi`).
3. Installer les dépendances :

   ```bash
   composer install
   ```

4. Créer une base MySQL/MariaDB et importer le script SQL du projet lorsqu'il sera ajouté au dépôt.
5. Configurer la connexion à la base de données avec `DB_HOST`, `DB_NAME`, `DB_USER` et `DB_PASS`, ou utiliser les valeurs locales prévues dans `config/database.php`.
6. Configurer les paramètres SMTP privés hors du répertoire public. Ne jamais publier les mots de passe, clés SMTP ou clés HMAC.
7. Configurer MongoDB si la journalisation est utilisée.
8. Ouvrir l'application depuis l'URL locale correspondant au répertoire d'installation.

## Déploiement

L'application est hébergée sur Alwaysdata. Le code est synchronisé depuis la branche `main` du dépôt GitHub. La base relationnelle est hébergée sur le service MariaDB d'Alwaysdata.

Les identifiants MySQL et SMTP sont conservés hors du dépôt public.

## Sécurité

- Requêtes SQL préparées avec PDO.
- Mots de passe stockés sous forme de hachages.
- Gestion des sessions et contrôle d'accès selon les rôles.
- Protection CSRF sur les formulaires concernés.
- Échappement HTML des données affichées.
- Validation des données côté serveur.
- Jetons de réinitialisation de mot de passe à durée limitée.
- Configuration sensible séparée du code public.

## Tests réalisés sur Alwaysdata

- Inscription d'un utilisateur.
- Connexion et accès à l'espace personnel.
- Création et affichage de personnages.
- Modération et duplication de personnages.
- Activation et connexion d'un administrateur.
- Création et connexion d'un employé.
- Envoi SMTP depuis le serveur via Brevo.

## Limites connues

L'extension PHP MongoDB n'est pas disponible dans l'environnement PHP CLI Alwaysdata testé. L'installation des dépendances a donc été effectuée avec `--ignore-platform-req=ext-mongodb`. Cette option ne rend pas MongoDB opérationnel : la journalisation en production reste à vérifier et à configurer.

## Documentation complémentaire

Les scripts SQL, le manuel utilisateur, les maquettes, la charte graphique et la documentation technique sont des livrables complémentaires du projet.

## Licence

Projet pédagogique réalisé pour une évaluation Studi. Aucune licence de réutilisation n'est accordée explicitement.
