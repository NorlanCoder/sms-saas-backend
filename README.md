# SMS SaaS Backend

Backend API Laravel pour la plateforme SMS SaaS.

## Analyse rapide du backend

Ce backend est deja structure pour un usage production-like avec separation des roles et des flux principaux:

- Authentification API via Sanctum pour les entreprises, admins et super admins.
- Envoi SMS en mode unitaire et batch (queue) avec journalisation des envois.
- Gestion du solde (recharge/debit) avec transactions atomiques.
- Verification de signature RSA pour un endpoint d'envoi SMS dedie a l'integration machine-to-machine.
- Gestion des pays activables par entreprise et validation des SENDER_IDs.

Points importants observes:

- Le provider SMS actuel est un stub dans `SmsProviderService` (retour succes simule).
- Le rechargement est integre via FedaPay (flux initie puis confirmation).
- Les batches SMS reposent sur la queue Laravel, donc un worker est necessaire pour traiter les jobs.

## Stack technique

- PHP 8.3+
- Laravel 13
- Laravel Sanctum (tokens API)
- Queue Laravel (database par defaut)
- SQLite en local par defaut (configurable MySQL/PostgreSQL)

## Fonctionnalites metier

- Onboarding entreprise:
- Inscription, connexion, profil, changement mot de passe, suppression compte.
- Generation de paire RSA a l'inscription (cle publique stockee, cle privee retournee une seule fois).

- Credits:
- Consultation du solde.
- Rechargement via FedaPay (mobile money) avec statut de paiement.
- Historique des transactions (filtres type et periode).

- SMS:
- Envoi unitaire (synchrone) avec debit immediat si succes.
- Envoi batch (asynchrone) avec `batch_id` et endpoint de statut.
- Verification du pays destinataire actif pour l'entreprise.
- Verification d'un `sender_id` valide.

- Administration:
- Espaces `admin` et `super-admin` avec endpoints dedies.
- Supervision entreprises, pays, sender IDs, logs SMS, transactions.

## Installation locale

Depuis le dossier `sms-saas-backend`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Si vous utilisez SQLite en local:

```bash
touch database/database.sqlite
```

Puis dans `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/chemin/absolu/vers/sms-saas-backend/database/database.sqlite
```

Lancement du backend:

```bash
php artisan serve
```

Pour traiter les SMS batch:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Option pratique en dev (serveur + queue + logs + vite):

```bash
composer run dev
```

## Variables d'environnement importantes

- `APP_URL` URL de l'API (ex: `http://localhost:8000`)
- `DB_*` configuration base de donnees
- `QUEUE_CONNECTION` transport de queue (`database` par defaut)
- `FEDAPAY_SECRET_KEY` cle API secrete FedaPay
- `FEDAPAY_PUBLIC_KEY` cle publique FedaPay
- `FEDAPAY_ENVIRONMENT` `sandbox` ou `live`
- `FEDAPAY_CALLBACK_URL` URL webhook FedaPay

## Comptes seedes (dev)

Apres `php artisan migrate --seed`:

- Admin:
- email: `admin@sms-saas.com`
- password: `Admin@1234`

- Super Admin:
- email: `superadmin@sms-saas.com`
- password: `SuperAdmin@1234`

## Base URL API

- Entreprise: `/api/v1`
- Admin: `/api/admin`
- Super Admin: `/api/super-admin`

## Endpoints principaux

### Entreprise publiques

- `POST /api/v1/register`
- `POST /api/v1/login`
- `GET /api/v1/countries`

### Entreprise protegees (Bearer token Sanctum)

- `POST /api/v1/logout`
- `POST /api/v1/logout-all`
- `GET /api/v1/profile`
- `PUT /api/v1/profile`
- `PUT /api/v1/profile/password`
- `DELETE /api/v1/profile`
- `GET /api/v1/keys`
- `POST /api/v1/keys/regenerate`
- `GET /api/v1/credits/balance`
- `POST /api/v1/credits/recharge`
- `GET /api/v1/credits/recharge/{transactionId}/status`
- `GET /api/v1/credits/transactions`
- `POST /api/v1/sms/send`
- `GET /api/v1/sms/logs`
- `GET /api/v1/sms/status/{batchId}`
- `GET /api/v1/dashboard/stats`
- `GET /api/v1/reports/export`
- `GET /api/v1/countries/active`
- `POST /api/v1/countries/activate`
- `POST /api/v1/countries/deactivate`
- `GET /api/v1/sender-ids`
- `POST /api/v1/sender-ids`
- `GET /api/v1/sender-ids/{id}`
- `DELETE /api/v1/sender-ids/{id}`

### Webhook paiement (publique)

- `POST /api/v1/webhooks/fedapay`

### Endpoint SMS signe (integration serveur-a-serveur)

- `POST /api/v1/send-sms`

Protections appliquees:

- Middleware `verify.api.signature`
- Rate limit `throttle:60,1`

Headers obligatoires:

- `X-Public-Key`
- `X-Signature`
- `X-Timestamp` (fenetre de 5 minutes)

La signature est verifiee sur la concatenation:

```text
METHOD + URL + TIMESTAMP
```

Exemple de generation de signature cote client (pseudo-code):

```text
data = "POST" + "https://api.example.com/api/v1/send-sms" + timestamp
signature = base64( RSA_SHA256_SIGN(private_key, data) )
```

## Exemples de payload

Inscription:

```json
{
	"nom": "Banque Test",
	"email": "banque@test.com",
	"pays": "BEN",
	"telephone": "+22996000000",
	"password": "Test@1234",
	"password_confirmation": "Test@1234"
}
```

Envoi SMS unitaire:

```json
{
	"to": "+22996000000",
	"message": "Votre code OTP est 123456",
	"sender_id": "BANQUETEST"
}
```

Envoi SMS batch:

```json
{
	"to": ["+22996000000", "+2250700000000"],
	"message": "Message de campagne",
	"sender_id": "BANQUETEST"
}
```

Recharge credits:

```json
{
	"montant": 1000,
	"methode": "mobile_money",
	"phone": "+22996000000",
	"status": "pending"
}
```

## Validation fonctionnelle rapide

Un script de smoke test est disponible:

```bash
bash tests/api-tests.sh
```

Il couvre les principaux parcours entreprise et admin.

## Tests et qualite

Executer les tests:

```bash
php artisan test
```

## Notes d'architecture

- Les debits de credits sont encapsules en transaction SQL pour limiter les races.
- En batch, chaque destinataire est journalise avec son statut final (`envoye` ou `echoue`).
- En cas de solde insuffisant sur batch, les destinataires restants sont marques en echec.
- Le endpoint signe permet une integration API sans session utilisateur, basee sur cle publique + signature.

## Limites actuelles et prochaines etapes

- Brancher un vrai provider SMS HTTP dans `SmsProviderService`.
- Ajouter des tests d'integration pour la verification RSA (`verify.api.signature`).
- Ajouter des retries/monitoring de jobs queue en production.
