# SMS SaaS Backend

API backend de la plateforme SaaS B2B d'envoi de SMS, développée avec Laravel 13 et PHP 8.3. Elle expose un ensemble de routes REST versionnées permettant aux entreprises clientes de s'inscrire, de recharger des crédits prépayés, de configurer leurs destinations et leurs SENDER_ID, et d'envoyer des SMS via une interface web ou directement par API sécurisée (signature RSA). Un espace d'administration multi-rôles permet la supervision globale de la plateforme.

## Aperçu

Cette API gère :

- l'inscription et la connexion des entreprises
- la génération de clés RSA
- les soldes et les transactions de crédits
- l'envoi de SMS simple et en lot (`batch`)
- les `SENDER_ID` et les pays autorisés par entreprise
- les statistiques du tableau de bord
- l'administration et la supervision globale (`multi-rôles`)

## Stack technique

| Technologie | Rôle |
| --- | --- |
| `PHP 8.3` | Langage de programmation |
| `Laravel 13` | Framework principal |
| `Laravel Sanctum` | Authentification par token |
| `SQLite` | Base de données par défaut |
| `PHPUnit` | Tests automatisés |
| `Vite` | Compilation des assets frontend |

## Fonctionnalités

- API REST versionnée (`v1`, `admin`, `super-admin`)
- Authentification par token via `Laravel Sanctum`
- Signature RSA pour les appels API sécurisés
- Vérification du solde avant chaque envoi de SMS
- Gestion des crédits et des recharges (`PawaPay`)
- Logs SMS et export de rapports
- Administration multi-rôles : `admin` et `super_admin`
- Jobs Laravel pour les envois en masse via une queue

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
```

## Configuration

Le projet fournit un fichier `.env.example`.

Variables importantes :

| Variable | Description |
| --- | --- |
| `APP_URL` | URL publique de l'application |
| `DB_CONNECTION` | Driver de base de données (`sqlite`, `mysql`, etc.) |
| `DB_DATABASE` | Chemin ou nom de la base de données |
| `QUEUE_CONNECTION` | Driver de queue (`sync`, `database`, `redis`, etc.) |
| `PAWAPAY_API_KEY` | Clé API PawaPay pour les recharges de crédits |
| `PAWAPAY_BASE_URL` | URL de l'API PawaPay |
| `PAWAPAY_SANDBOX` | Active ou non le mode sandbox PawaPay |

## Lancement en local

La commande suivante lance simultanément le serveur Laravel, le worker de queue, les logs et Vite :

```bash
composer run dev
```

Alternative manuelle :

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

L'API est accessible sur `http://localhost:8000`.

## Comptes créés par les seeders

| Rôle | Identifiants |
| --- | --- |
| Administrateur | `admin@sms-saas.com` / `Admin@1234` |
| Super administrateur | `superadmin@sms-saas.com` / `SuperAdmin@1234` |

## Endpoints principaux

### Entreprises

- `POST /api/v1/register`
- `POST /api/v1/login`
- `POST /api/v1/logout`
- `POST /api/v1/logout-all`
- `GET /api/v1/profile`
- `PUT /api/v1/profile`
- `PUT /api/v1/profile/password`
- `DELETE /api/v1/profile`

### Crédits

- `GET /api/v1/credits/balance`
- `POST /api/v1/credits/recharge`
- `GET /api/v1/credits/transactions`

### SMS

- `POST /api/v1/sms/send`
- `GET /api/v1/sms/logs`
- `GET /api/v1/sms/status/{batchId}`
- `POST /api/v1/send-sms`

### Pays et expéditeurs

- `GET /api/v1/countries`
- `GET /api/v1/countries/active`
- `POST /api/v1/countries/activate`
- `POST /api/v1/countries/deactivate`
- `GET /api/v1/sender-ids`
- `POST /api/v1/sender-ids`
- `GET /api/v1/sender-ids/{id}`
- `DELETE /api/v1/sender-ids/{id}`

### Administration

- `POST /api/admin/login`
- `POST /api/admin/logout`
- `GET /api/admin/companies`
- `GET /api/admin/sender-ids`
- `GET /api/admin/countries`
- `GET /api/admin/sms/logs`
- `GET /api/admin/transactions`

### Super-administration

- `POST /api/super-admin/login`
- `POST /api/super-admin/logout`

## Tests

```bash
php artisan test
```

ou

```bash
composer test
```

## Structure du projet

```text
app/         Logique métier (Models, Controllers, Jobs, Services...)
bootstrap/   Initialisation du framework
config/      Fichiers de configuration
database/    Migrations, factories et seeders
public/      Point d'entrée HTTP
resources/   Vues et assets
routes/      Définition des routes
tests/       Tests unitaires et fonctionnels
```

## Notes techniques

- `SmsProviderService` utilise actuellement une réponse simulée pour l'envoi SMS.
- `PawaPay` est intégré pour les recharges de crédits, avec un mode sandbox activable via `PAWAPAY_SANDBOX`.
- Les envois en lot transitent par une queue Laravel pour garantir la fiabilité et éviter les timeouts.
- La signature RSA est vérifiée via un middleware dédié appliqué aux routes d'envoi API.
