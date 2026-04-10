# SMS SaaS Backend

Backend API de la plateforme SaaS B2B d'envoi de SMS.  
Ce service, construit avec **Laravel 13** et **PHP 8.3**, permet aux entreprises de :

- creer un compte et se connecter ;
- gerer leurs credits prepayes ;
- activer les pays de destination ;
- administrer leurs `SENDER_ID` ;
- envoyer des SMS en mode simple, lot ou via API signee RSA ;
- suivre les logs, transactions et statistiques.

## Apercu

Le backend expose une API REST versionnee autour de trois espaces :

- **Entreprise** : authentification, profil, credits, pays, `SENDER_ID`, SMS, rapports.
- **Administration** : supervision operationnelle des entreprises, logs, transactions et validation metier.
- **Super administration** : acces global avec privileges eleves.

## Stack technique

| Outil | Version |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 13 |
| Authentification | Sanctum |
| Base de donnees | SQLite ou MySQL |
| Tests | PHPUnit |
| Front tooling | Vite, Tailwind CSS |
| Paiement / recharge | PawaPay |

## Fonctionnalites principales

- Authentification entreprise via token Sanctum
- Gestion du profil et du mot de passe
- Regeneration des cles API
- Recharge et suivi des credits
- Envoi de SMS unitaire et en masse
- Signature RSA pour l'envoi via API externe
- Logs SMS, historique de transactions et export de rapports
- Administration multi-roles

## Prerequis

Avant de lancer le projet, assurez-vous d'avoir :

- `PHP >= 8.3`
- `Composer 2.x`
- `Node.js >= 18`
- `npm >= 9`
- `SQLite` ou `MySQL 8+`

## Installation rapide

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
```

Si vous utilisez **MySQL**, adaptez les variables `DB_*` dans le fichier `.env`.

## Configuration `.env`

### Variables principales

| Variable | Description |
| --- | --- |
| `APP_URL` | URL publique de l'application |
| `DB_CONNECTION` | `sqlite` ou `mysql` |
| `DB_HOST` | Hote MySQL |
| `DB_PORT` | Port MySQL |
| `DB_DATABASE` | Nom de la base ou chemin SQLite |
| `DB_USERNAME` | Utilisateur MySQL |
| `DB_PASSWORD` | Mot de passe MySQL |
| `QUEUE_CONNECTION` | `database`, `sync` ou autre driver configure |
| `PAWAPAY_API_KEY` | Cle API PawaPay |
| `PAWAPAY_BASE_URL` | URL de l'API PawaPay |
| `PAWAPAY_SANDBOX` | `true` ou `false` |

### Exemple de configuration SQLite

```env
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
PAWAPAY_SANDBOX=true
```

### Exemple de configuration MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sms_saas
DB_USERNAME=root
DB_PASSWORD=secret
QUEUE_CONNECTION=database
```

## Base de donnees et seeders

### Executer les migrations

```bash
php artisan migrate
php artisan migrate --seed
php artisan migrate:fresh --seed
```

### Comptes crees par les seeders

| Role | Email | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@sms-saas.com` | `Admin@1234` |
| Super administrateur | `superadmin@sms-saas.com` | `SuperAdmin@1234` |

## Lancement en local
 
### Mode recommande

```bash
composer run dev
```

Cette commande lance en parallele :

- le serveur Laravel ;
- le worker de queue ;
- les logs via Laravel Pail ;
- Vite pour les assets.

### Mode manuel

Dans des terminaux separes :

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

API disponible par defaut sur : `http://localhost:8000`

## Authentification et securite

### Sanctum

Les routes entreprises, admin et super admin protegees utilisent l'authentification par token avec **Laravel Sanctum**.

### Signature RSA

Lors de l'inscription d'une entreprise, une paire de cles RSA est generee.  
La route d'envoi externe verifie la signature grace au middleware `verify.api.signature`.

Exemple de signature cote client :

```php
openssl_sign(
    json_encode($payload),
    $signature,
    $privateKey,
    OPENSSL_ALGO_SHA256
);

$signatureBase64 = base64_encode($signature);
```

## Routes API

### Entreprise

| Methode | Route | Description |
| --- | --- | --- |
| `POST` | `/api/v1/register` | Inscription d'une entreprise |
| `POST` | `/api/v1/login` | Connexion entreprise |
| `POST` | `/api/v1/logout` | Revocation du token courant |
| `POST` | `/api/v1/logout-all` | Deconnexion de tous les appareils |
| `GET` | `/api/v1/profile` | Recuperer le profil |
| `PUT` | `/api/v1/profile` | Mettre a jour le profil |
| `PUT` | `/api/v1/profile/password` | Modifier le mot de passe |
| `DELETE` | `/api/v1/profile` | Supprimer le compte |
| `GET` | `/api/v1/keys` | Lister les cles API |
| `POST` | `/api/v1/keys/regenerate` | Regenerer les cles API |
| `GET` | `/api/v1/credits/balance` | Consulter le solde |
| `POST` | `/api/v1/credits/recharge` | Recharger les credits |
| `GET` | `/api/v1/credits/transactions` | Historique des transactions |
| `GET` | `/api/v1/dashboard/stats` | Statistiques du dashboard |
| `POST` | `/api/v1/sms/send` | Envoi simple ou en lot |
| `GET` | `/api/v1/sms/logs` | Historique des SMS |
| `GET` | `/api/v1/sms/status/{batchId}` | Statut d'un batch |
| `GET` | `/api/v1/reports/export` | Export de rapport |
| `GET` | `/api/v1/countries` | Lister les pays disponibles |
| `GET` | `/api/v1/countries/active` | Pays actifs pour l'entreprise |
| `POST` | `/api/v1/countries/activate` | Activer un pays |
| `POST` | `/api/v1/countries/deactivate` | Desactiver un pays |
| `GET` | `/api/v1/sender-ids` | Lister les `SENDER_ID` |
| `POST` | `/api/v1/sender-ids` | Creer un `SENDER_ID` |
| `GET` | `/api/v1/sender-ids/{id}` | Detail d'un `SENDER_ID` |
| `DELETE` | `/api/v1/sender-ids/{id}` | Supprimer un `SENDER_ID` |

### Envoi externe signe

| Methode | Route | Description |
| --- | --- | --- |
| `POST` | `/api/v1/send-sms` | Envoi via signature RSA + rate limiting |

### Administration

| Methode | Route | Description |
| --- | --- | --- |
| `POST` | `/api/admin/login` | Connexion admin |
| `POST` | `/api/admin/logout` | Deconnexion admin |
| `GET` | `/api/admin/companies` | Liste des entreprises |
| `GET` | `/api/admin/companies/{id}` | Detail d'une entreprise |
| `PUT` | `/api/admin/companies/{id}/status` | Changer le statut d'une entreprise |
| `GET` | `/api/admin/sender-ids` | Vue globale des `SENDER_ID` |
| `PUT` | `/api/admin/sender-ids/{id}/approve` | Approuver un `SENDER_ID` |
| `PUT` | `/api/admin/sender-ids/{id}/reject` | Rejeter un `SENDER_ID` |
| `PUT` | `/api/admin/sender-ids/{id}/suspend` | Suspendre un `SENDER_ID` |
| `GET` | `/api/admin/countries` | Vue globale des pays |
| `PUT` | `/api/admin/countries/{id}/tariff` | Modifier le tarif SMS |
| `PUT` | `/api/admin/countries/{id}/status` | Activer ou desactiver un pays |
| `GET` | `/api/admin/sms/logs` | Logs SMS globaux |
| `GET` | `/api/admin/transactions` | Transactions globales |

### Super administration

| Methode | Route | Description |
| --- | --- | --- |
| `POST` | `/api/super-admin/login` | Connexion super admin |
| `POST` | `/api/super-admin/logout` | Deconnexion super admin |
| `GET` | `/api/super-admin/companies` | Liste des entreprises |
| `GET` | `/api/super-admin/companies/{id}` | Detail d'une entreprise |
| `PUT` | `/api/super-admin/companies/{id}/status` | Changer le statut d'une entreprise |
| `GET` | `/api/super-admin/sender-ids` | Vue globale des `SENDER_ID` |
| `PUT` | `/api/super-admin/sender-ids/{id}/approve` | Approuver un `SENDER_ID` |
| `PUT` | `/api/super-admin/sender-ids/{id}/reject` | Rejeter un `SENDER_ID` |
| `PUT` | `/api/super-admin/sender-ids/{id}/suspend` | Suspendre un `SENDER_ID` |
| `GET` | `/api/super-admin/countries` | Vue globale des pays |
| `PUT` | `/api/super-admin/countries/{id}/tariff` | Modifier le tarif SMS |
| `PUT` | `/api/super-admin/countries/{id}/status` | Activer ou desactiver un pays |
| `GET` | `/api/super-admin/sms/logs` | Logs SMS globaux |
| `GET` | `/api/super-admin/transactions` | Transactions globales |

## Structure du projet

```text
app/
  Http/Controllers/     Controleurs API, admin et super admin
  Http/Middleware/      Middlewares de securite et journalisation
  Http/Requests/        Validation des requetes
  Jobs/                 Traitements asynchrones
  Models/               Modeles Eloquent
  Services/             Services metier (SMS, credits, RSA, PawaPay)
database/
  migrations/           Structure de la base de donnees
  seeders/              Donnees initiales
routes/api.php          Definition des routes API
tests/                  Tests unitaires et fonctionnels
config/                 Configuration Laravel
public/                 Point d'entree HTTP
```

## Tests

```bash
php artisan test
php artisan test --filter NomDuTest
composer test
```

Le projet contient des tests unitaires et des tests fonctionnels pour les flux critiques.

## Deploiement

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:work --daemon --tries=3
```

Configuration recommandee en production :

- `APP_ENV=production`
- `APP_DEBUG=false`
- variables `DB_*` correctement renseignees
- worker de queue supervise via **Supervisor** ou un service equivalent

## Notes techniques

- `SmsProviderService` repose actuellement sur une reponse simulee a remplacer par un fournisseur reel.
- `PawaPayService` gere les recharges de credits avec mode sandbox activable.
- les envois en masse passent par une queue Laravel pour eviter les timeouts HTTP ;
- la verification RSA protege l'endpoint d'envoi externe.

## Contribution

```bash
git checkout -b feat/ma-fonctionnalite
php artisan test
```

Bonnes pratiques attendues :

- respecter les conventions Laravel ;
- ajouter ou mettre a jour les tests ;
- ouvrir une Pull Request claire vers la branche cible ;
- decrire precisement les changements fonctionnels et techniques.
