#!/usr/bin/env bash
# Smoke test against local Docker stack (http://localhost:8080)
set -euo pipefail

BASE="${BASE_URL:-http://localhost:8080}"
COOKIE="$(mktemp)"
HTML="$(mktemp)"
trap 'rm -f "$COOKIE" "$HTML"' EXIT

pass=0
fail=0
STAMP=$(date +%s)
PROJ_NAME="Smoke Project ${STAMP}"
INVITE_EMAIL="pending-${STAMP}@example.com"
REQUEST_EMAIL="requester-${STAMP}@example.com"

ok() { echo "PASS  $1"; pass=$((pass + 1)); }
bad() { echo "FAIL  $1"; fail=$((fail + 1)); }
assert() { local name="$1"; shift; if "$@"; then ok "$name"; else bad "$name"; fi; }
csrf_from() { sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' "$1" | head -1; }

echo "== Smoke test @ $BASE =="

if [[ "${RESET_INSTALL:-0}" == "1" ]]; then
  rm -f config/config.php install/.installed
fi

# Best-effort DB cleanup of prior smoke data (keeps admin)
docker exec projecttracker-db-1 mysql -utracker -ptracker tracker -e \
  "SET FOREIGN_KEY_CHECKS=0;
   TRUNCATE TABLE task_comments;
   TRUNCATE TABLE subtasks;
   TRUNCATE TABLE tasks;
   TRUNCATE TABLE project_members;
   TRUNCATE TABLE projects;
   DELETE FROM users WHERE email <> 'admin@example.com';
   SET FOREIGN_KEY_CHECKS=1;" >/dev/null 2>&1 || true

code=$(curl -sS -o "$HTML" -w "%{http_code}" "$BASE/install/install.php")
assert "install page 200" test "$code" = "200"

if [[ ! -f config/config.php ]]; then
  code=$(curl -sS -o "$HTML" -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" \
    -X POST "$BASE/install/install.php" \
    --data-urlencode "db_host=db" \
    --data-urlencode "db_name=tracker" \
    --data-urlencode "db_user=tracker" \
    --data-urlencode "db_pass=tracker" \
    --data-urlencode "app_url=http://localhost:8080" \
    --data-urlencode "timezone=UTC" \
    --data-urlencode "admin_name=Admin" \
    --data-urlencode "admin_email=admin@example.com" \
    --data-urlencode "admin_pass=secret12")
  assert "install HTTP 200" test "$code" = "200"
  assert "config written" test -f config/config.php
  if grep -qiE 'complete|login|locked|already installed' "$HTML"; then ok "install message"; else bad "install message"; head -40 "$HTML"; fi
else
  ok "config already present"
fi

code=$(curl -sS -o "$HTML" -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/login.php")
assert "login page 200" test "$code" = "200"
csrf=$(csrf_from "$HTML")
assert "login csrf" test -n "$csrf"

curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" -L \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode "email=admin@example.com" \
  --data-urlencode "password=secret12" \
  "$BASE/login.php" >/dev/null
if grep -q 'Dashboard' "$HTML"; then ok "login succeeds"; else bad "login succeeds"; head -40 "$HTML"; fi

code=$(curl -sS -o "$HTML" -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/index.php")
assert "dashboard 200" test "$code" = "200"

curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" "$BASE/projects.php" >/dev/null
csrf=$(csrf_from "$HTML")
# Important: do NOT use -X POST with -L (forces POST on redirect → CSRF 403)
curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" -L \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode "action=create" \
  --data-urlencode "name=${PROJ_NAME}" \
  --data-urlencode "description=Created by smoke test" \
  --data-urlencode "color=#0f766e" \
  "$BASE/projects.php" >/dev/null
if grep -qF "$PROJ_NAME" "$HTML" || grep -qi 'Project created' "$HTML"; then ok "project created"; else bad "project created"; fi

curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" "$BASE/list.php" >/dev/null
proj_id=$(python3 - "$HTML" <<'PY'
import re, sys
html = open(sys.argv[1]).read()
# Prefer the unique smoke project option
m = re.search(r'<option value="(\d+)">Smoke Project [^<]+</option>', html)
if not m:
    m = re.search(r'<option value="(\d+)">', html)
print(m.group(1) if m else '')
PY
)
assert "project id found" test -n "$proj_id"

token=$(python3 - "$HTML" <<'PY'
import re, sys
html = open(sys.argv[1]).read()
m = re.search(r'CSRF_TOKEN\s*=\s*"([^"]+)"', html)
print(m.group(1) if m else '')
PY
)
assert "api csrf token" test -n "$token"

task_json=$(curl -sS -c "$COOKIE" -b "$COOKIE" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $token" \
  -X POST "$BASE/api/tasks.php" \
  -d "{\"title\":\"Smoke Task\",\"project_id\":$proj_id,\"status\":\"todo\",\"priority\":\"high\"}")
if echo "$task_json" | grep -q 'Smoke Task'; then ok "task created"; else bad "task created"; echo "$task_json"; fi
task_id=$(printf '%s' "$task_json" | python3 -c 'import json,sys
try: print(json.load(sys.stdin)["task"]["id"])
except Exception: print("")')
assert "task id" test -n "$task_id"

sub_json=$(curl -sS -c "$COOKIE" -b "$COOKIE" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $token" \
  -X POST "$BASE/api/subtasks.php" \
  -d "{\"action\":\"create\",\"task_id\":$task_id,\"title\":\"Smoke subtask\"}")
if echo "$sub_json" | grep -q 'Smoke subtask'; then ok "subtask created"; else bad "subtask created"; echo "$sub_json"; fi

com_json=$(curl -sS -c "$COOKIE" -b "$COOKIE" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $token" \
  -X POST "$BASE/api/comments.php" \
  -d "{\"action\":\"create\",\"task_id\":$task_id,\"body\":\"Smoke comment\"}")
if echo "$com_json" | grep -q 'Smoke comment'; then ok "comment created"; else bad "comment created"; echo "$com_json"; fi

move_json=$(curl -sS -c "$COOKIE" -b "$COOKIE" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $token" \
  -X POST "$BASE/api/tasks.php" \
  -d "{\"id\":$task_id,\"status\":\"in_progress\",\"position\":0,\"ordered_ids\":[$task_id]}")
if echo "$move_json" | grep -q 'in_progress'; then ok "kanban move"; else bad "kanban move"; echo "$move_json"; fi

code=$(curl -sS -o "$HTML" -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/list.php?project=$proj_id")
assert "list filter page" test "$code" = "200"
if grep -q 'Smoke Task' "$HTML"; then ok "list filter shows task"; else bad "list filter shows task"; fi

code=$(curl -sS -o "$HTML" -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/kanban.php?project=$proj_id")
assert "kanban page" test "$code" = "200"
if grep -q 'Smoke Task' "$HTML"; then ok "kanban shows task"; else bad "kanban shows task"; fi

curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" "$BASE/team.php" >/dev/null
csrf=$(csrf_from "$HTML")
curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" -L \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode "action=invite" \
  --data-urlencode "name=Pending User" \
  --data-urlencode "email=${INVITE_EMAIL}" \
  --data-urlencode "role=member" \
  "$BASE/team.php" >/dev/null
if grep -qiE 'pending|Invite created' "$HTML"; then ok "invite pending"; else bad "invite pending"; fi

rm -f "$COOKIE"
curl -sS -o "$HTML" -c "$COOKIE" "$BASE/request-access.php" >/dev/null
csrf=$(csrf_from "$HTML")
curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode "name=Requester" \
  --data-urlencode "email=${REQUEST_EMAIL}" \
  --data-urlencode "password=secret12" \
  "$BASE/request-access.php" >/dev/null
if grep -qiE 'approve|submitted|request' "$HTML"; then ok "request access ok"; else bad "request access ok"; head -20 "$HTML"; fi

curl -sS -o "$HTML" -c "$COOKIE" "$BASE/login.php" >/dev/null
csrf=$(csrf_from "$HTML")
curl -sS -o "$HTML" -c "$COOKIE" -b "$COOKIE" \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode "email=${REQUEST_EMAIL}" \
  --data-urlencode "password=secret12" \
  "$BASE/login.php" >/dev/null
if grep -qiE 'waiting for admin approval|pending' "$HTML"; then ok "pending blocked"; else bad "pending blocked"; head -20 "$HTML"; fi

curl -sS -o "$HTML" \
  --data-urlencode "db_host=db" \
  --data-urlencode "db_name=tracker" \
  --data-urlencode "db_user=tracker" \
  --data-urlencode "db_pass=tracker" \
  --data-urlencode "admin_name=X" \
  --data-urlencode "admin_email=x2@example.com" \
  --data-urlencode "admin_pass=secret12" \
  "$BASE/install/install.php" >/dev/null
if grep -qiE 'Already installed|locked|Go to login' "$HTML"; then ok "install locked"; else bad "install locked"; fi

echo
echo "Result: $pass passed, $fail failed"
[[ "$fail" -eq 0 ]]
