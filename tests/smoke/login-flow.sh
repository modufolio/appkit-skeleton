#!/usr/bin/env bash
#
# End-to-end smoke test of the login flow against a running server.
#
# Exercises what unit tests don't cover: session cookie emission, session id
# migration during login, no state bleeding into cookieless requests, and
# logout invalidation.
#
# Usage: BASE_URL=http://127.0.0.1:8000 tests/smoke/login-flow.sh <email> <password>

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
EMAIL="${1:?usage: login-flow.sh <email> <password>}"
PASSWORD="${2:?usage: login-flow.sh <email> <password>}"

WORK_DIR=$(mktemp -d)
JAR="$WORK_DIR/cookies.txt"
trap 'rm -rf "$WORK_DIR"' EXIT

fail() {
    echo "FAIL: $1" >&2
    exit 1
}

pass() {
    echo "ok: $1"
}

# ── 1. Login page renders and starts a session ──────────────────────────────
CSRF=$(curl -sf -c "$JAR" "$BASE_URL/login" | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
[ -n "$CSRF" ] || fail "no CSRF token on the login page"
SID_ANON=$(grep PHPSESSID "$JAR" | awk '{print $NF}')
[ -n "$SID_ANON" ] || fail "login page did not set a session cookie"
pass "login page renders with CSRF token and session cookie"

# ── 2. Login succeeds and the MIGRATED session id reaches the client ────────
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" \
    -d "email=$EMAIL&password=$PASSWORD&_csrf_token=$CSRF" "$BASE_URL/login")
[ "$STATUS" = "302" ] || fail "login POST returned $STATUS, expected 302"
SID_AUTH=$(grep PHPSESSID "$JAR" | awk '{print $NF}')
[ -n "$SID_AUTH" ] || fail "no session cookie after login"
[ "$SID_AUTH" != "$SID_ANON" ] || fail "session id was not migrated on login (fixation risk), or the migrated id never reached the client"
pass "login migrates the session id and sends it to the client"

# ── 3. The migrated cookie authenticates ────────────────────────────────────
BODY=$(curl -sf -b "$JAR" "$BASE_URL/")
echo "$BODY" | grep -q "Signed in as $EMAIL" || fail "authenticated page does not show 'Signed in as $EMAIL'"
pass "authenticated page renders with the migrated cookie"

# ── 4. A stable session keeps a stable id ───────────────────────────────────
# The native runtime re-sends the session cookie on every response (PHP
# re-emits after the explicit session_id() call), which is harmless as long
# as the id never changes. A changing id here would mean broken sessions.
RESENT=$(curl -sf -i -b "$JAR" "$BASE_URL/" | grep -i "^set-cookie" | grep -o 'PHPSESSID=[a-z0-9]*' | cut -d= -f2 || true)
if [ -n "$RESENT" ] && [ "$RESENT" != "$SID_AUTH" ]; then
    fail "session id changed on a stable session ($SID_AUTH -> $RESENT)"
fi
pass "session id stays stable across requests"

# ── 5. State isolation: a cookieless request must be anonymous ──────────────
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/")
[ "$STATUS" = "302" ] || fail "cookieless request after an authenticated one returned $STATUS, expected 302 to /login — state leaked?"
pass "cookieless request stays anonymous"

# ── 6. Logout invalidates the session ───────────────────────────────────────
LCSRF=$(curl -sf -b "$JAR" "$BASE_URL/" | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" \
    -d "_csrf_token=$LCSRF" "$BASE_URL/logout")
[ "$STATUS" = "302" ] || fail "logout POST returned $STATUS, expected 302"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" "$BASE_URL/")
[ "$STATUS" = "302" ] || fail "still authenticated after logout"
pass "logout invalidates the session"

echo "smoke test passed"
