#!/bin/bash

BASE_URL="http://localhost:8000/api/v1"
ADMIN_URL="http://localhost:8000/api/admin"
TOKEN=""
ADMIN_TOKEN=""

echo "=========================================="
echo "TESTS API SMSSAAS"
echo "=========================================="

# ── TEST 1 : Inscription entreprise ──
echo ""
echo "TEST 1 — Inscription entreprise"
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/register" \
  -H "Content-Type: application/json" \
  -d '{
    "nom": "Banque Test",
    "email": "banque@test.com",
    "pays": "BEN",
    "telephone": "+22996000000",
    "password": "Test@1234",
    "password_confirmation": "Test@1234"
  }')
echo $REGISTER_RESPONSE | python3 -m json.tool
TOKEN=$(echo $REGISTER_RESPONSE | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))")
echo "Token récupéré : $TOKEN"

# ── TEST 2 : Connexion entreprise ──
echo ""
echo "TEST 2 — Connexion entreprise"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "banque@test.com", "password": "Test@1234"}')
echo $LOGIN_RESPONSE | python3 -m json.tool
TOKEN=$(echo $LOGIN_RESPONSE | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))")

# ── TEST 3 : Profil ──
echo ""
echo "TEST 3 — Profil entreprise"
curl -s -X GET "$BASE_URL/profile" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 4 : Clés API ──
echo ""
echo "TEST 4 — Clés API"
curl -s -X GET "$BASE_URL/keys" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 5 : Solde ──
echo ""
echo "TEST 5 — Solde actuel"
curl -s -X GET "$BASE_URL/credits/balance" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 6 : Rechargement ──
echo ""
echo "TEST 6 — Rechargement de crédits"
curl -s -X POST "$BASE_URL/credits/recharge" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"montant": 100, "methode": "mobile_money", "phone": "+22996000000"}' | python3 -m json.tool

# ── TEST 7 : Liste des pays ──
echo ""
echo "TEST 7 — Liste des pays"
curl -s -X GET "$BASE_URL/countries" | python3 -m json.tool

# ── TEST 8 : Activer un pays ──
echo ""
echo "TEST 8 — Activer Bénin (id=1)"
curl -s -X POST "$BASE_URL/countries/activate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"country_id": 1}' | python3 -m json.tool

# ── TEST 9 : Créer un SENDER_ID ──
echo ""
echo "TEST 9 — Créer SENDER_ID"
curl -s -X POST "$BASE_URL/sender-ids" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"nom": "BANQUETEST"}' | python3 -m json.tool

# ── TEST 10 : Liste SENDER_IDs ──
echo ""
echo "TEST 10 — Liste SENDER_IDs"
curl -s -X GET "$BASE_URL/sender-ids" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 11 : Statistiques dashboard ──
echo ""
echo "TEST 11 — Stats dashboard"
curl -s -X GET "$BASE_URL/dashboard/stats?periode=mois" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 12 : Historique transactions ──
echo ""
echo "TEST 12 — Historique transactions"
curl -s -X GET "$BASE_URL/credits/transactions" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 13 : Logs SMS ──
echo ""
echo "TEST 13 — Logs SMS"
curl -s -X GET "$BASE_URL/sms/logs" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# ── TEST 14 : Connexion admin ──
echo ""
echo "TEST 14 — Connexion admin"
ADMIN_LOGIN=$(curl -s -X POST "$ADMIN_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@sms-saas.com", "password": "Admin@1234"}')
echo $ADMIN_LOGIN | python3 -m json.tool
ADMIN_TOKEN=$(echo $ADMIN_LOGIN | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))")

# ── TEST 15 : Liste entreprises (admin) ──
echo ""
echo "TEST 15 — Liste entreprises (admin)"
curl -s -X GET "$ADMIN_URL/companies" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | python3 -m json.tool

# ── TEST 16 : Liste SENDER_IDs en attente (admin) ──
echo ""
echo "TEST 16 — SENDER_IDs en attente (admin)"
curl -s -X GET "$ADMIN_URL/sender-ids?statut=en_attente" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | python3 -m json.tool

# ── TEST 17 : Liste pays admin ──
echo ""
echo "TEST 17 — Pays (admin)"
curl -s -X GET "$ADMIN_URL/countries" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | python3 -m json.tool

# ── TEST 18 : Déconnexion ──
echo ""
echo "TEST 18 — Déconnexion"
curl -s -X POST "$BASE_URL/logout" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

echo ""
echo "=========================================="
echo "TESTS TERMINÉS"
echo "=========================================="
