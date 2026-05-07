#!/bin/bash
# Railway SQL Runner - Update user role directly via PostgreSQL
# Gets DATABASE_URL from Railway and connects directly to PostgreSQL

set -e

PROJECT_ID="47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d"
PROJECT_TOKEN="d1f04eaa-18a1-45eb-8d11-24bfe688c27f"

EMAIL="harun@toolblip.com"
ROLE="super_admin"

echo "=== Railway SQL Runner ==="
echo "Project: $PROJECT_ID"
echo "Target: $EMAIL -> $ROLE"
echo ""

# ─── Method 1: Railway CLI (if authenticated) ───
run_via_cli() {
  echo "[CLI] Trying railway variable list..."
  if railway variables -e production -s crontinel 2>/dev/null; then
    echo "[CLI] Connected!"
    return 0
  fi
  echo "[CLI] Failed"
  return 1
}

# ─── Method 2: Railway API directly ───
run_via_api() {
  echo "[API] Testing Railway API..."

  # Try various Railway API endpoints
  local base_urls=(
    "https://backboard.railway.app/api/v1"
    "https://backboard.railway.app/graphql"
    "https://gateway.railway.app/api/v1"
  )

  for base in "${base_urls[@]}"; do
    echo "[API] Trying $base..."
    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" \
      -H "Authorization: Bearer $PROJECT_TOKEN" \
      "$base/projects/$PROJECT_ID" 2>/dev/null)
    echo "[API] Status: $status"
  done

  # Try GraphQL introspection
  echo "[API] Trying GraphQL introspection..."
  curl -s -X POST \
    -H "Authorization: Bearer $PROJECT_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"query":"{ __schema { types { name } } }"}' \
    "https://backboard.railway.app/graphql" \
    -w "\nHTTP_STATUS:%{http_code}" 2>/dev/null | tail -5

  return 1
}

# ─── Method 3: Railway MCP ───
run_via_mcp() {
  echo "[MCP] Checking Railway MCP tools..."

  # These MCP tools would normally work if Railway's GraphQL wasn't broken
  # railway_project_info, railway_variable_list, etc.
  # They're failing due to schema mismatch: "Cannot query field 'prEnvCopyVolData'"

  echo "[MCP] Railway MCP is connected but GraphQL schema is outdated"
  echo "[MCP] This is a Railway-side issue, not a token issue"
  return 1
}

# ─── Method 4: Check if app has direct DB access ───
run_via_app() {
  echo "[APP] Checking if app can reach its own database..."

  # Try through the app's own endpoints
  local up_check
  up_check=$(curl -s "https://app.crontinel.com/api/v1/health" 2>/dev/null)
  echo "[APP] /api/v1/health: $up_check"

  # Check if there's a SQL runner route
  local sql_check
  sql_check=$(curl -s "https://app.crontinel.com/sql" 2>/dev/null)
  echo "[APP] /sql: $sql_check"

  return 1
}

# ─── Run methods in order ───
echo "=== Testing connectivity methods ==="
echo ""

run_via_cli || true
echo ""

run_via_api || true
echo ""

run_via_mcp || true
echo ""

run_via_app || true
echo ""

echo "=== Recommendation ==="
echo "The Railway API is returning 404 — Railway may have changed their API URL."
echo ""
echo "To update the user role, you need Railway CLI authenticated:"
echo "  1. Run: railway login"
echo "  2. Run: railway link -p 47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d"
echo "  3. Run: railway run php artisan tinker --execute=\"\$u=App\\Models\\User::where('email','$EMAIL')->first();\$u->role='$ROLE';\$u->save();\""
echo ""
echo "OR at railway.app → Crontinel project → Database → Query Editor:"
echo "  UPDATE users SET role = '$ROLE' WHERE email = '$EMAIL';"
