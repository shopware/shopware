#!/usr/bin/env bash
# Bring a freshly installed shop into a state worth screenshotting: demo content, refreshed indices,
# and a running web server.
#
# The agent's sandbox reaches host ports 80, 443 and 8080 only, and gh aw's own MCP gateway already
# owns 8080. So the shop listens unprivileged on APP_PORT and a root-owned forwarder publishes it on
# port 80, which is the address the agent uses.
#
# A clean install photographs as an empty page; demo data is what makes most reported states
# reachable by navigation alone.
#
# The shop is moved out of GITHUB_WORKSPACE first. The agent's sandbox mounts the workspace
# read-write, so anything left there is code the agent can rewrite before the host runs it — the
# source tree, vendor/autoload.php, a git hook. Outside the workspace the sandbox cannot see it at
# all, and the agent reaches the shop only over HTTP.
#
# Env: SHOP_SRC (default shop, where setup-shopware left it), SHOP_DIR (default
#      $RUNNER_TEMP/sw-screenshot-shop), APP_PORT (default 8000), SANDBOX_PORT (default 80),
#      SANDBOX_HOST (default host.docker.internal), DEMODATA (default true).
set -euo pipefail

SHOP_SRC=${SHOP_SRC:-shop}
SHOP_DIR=${SHOP_DIR:-${RUNNER_TEMP:-/tmp}/sw-screenshot-shop}

if [ -d "$SHOP_SRC" ] && [ ! -d "$SHOP_DIR" ]; then
  # setup-shopware leaves a server running against the old path. Moving the directory turns it into a
  # different project as far as the symfony CLI is concerned, so it no longer recognises it as
  # already-running and instead competes for the port.
  ( cd "$SHOP_SRC" && symfony server:stop >/dev/null 2>&1 || true )
  for _ in $(seq 1 15); do
    curl -s -o /dev/null --max-time 2 "http://localhost:${APP_PORT:-8000}/" || break
    sleep 1
  done

  echo "moving the shop out of the workspace: ${SHOP_SRC} -> ${SHOP_DIR}"
  mv "$SHOP_SRC" "$SHOP_DIR"
fi
SHOP_DIR=$(cd "$SHOP_DIR" && pwd)
APP_PORT=${APP_PORT:-8000}
SANDBOX_PORT=${SANDBOX_PORT:-80}
SANDBOX_HOST=${SANDBOX_HOST:-host.docker.internal}
DEMODATA=${DEMODATA:-true}
APP_URL="http://localhost:${APP_PORT}"

cd "$SHOP_DIR"

# A readiness failure that only reports a status code costs a whole run to diagnose. Print the
# response and the server's own log instead. Nothing in here may fail: it runs on the way to exit 1
# and a second error would only hide the first.
diagnose() {
  local url=$1 host=${2:-}
  echo "::group::diagnostics for ${url}"
  if [ -n "$host" ]; then
    curl -s -i --max-time 10 -H "Host: ${host}" "$url" | head -40 || true
  else
    curl -s -i --max-time 10 "$url" | head -40 || true
  fi
  for log in var/log/*.log; do
    [ -f "$log" ] || continue
    echo "--- ${log} ---"
    tail -30 "$log"
  done
  # `symfony server:log` streams and never returns, so read the file it writes instead.
  for log in "$HOME"/.symfony*/log/*.log; do
    [ -f "$log" ] || continue
    echo "--- ${log} ---"
    tail -30 "$log"
  done
  echo "::endgroup::"
}

# Persist to .env.local, not just GITHUB_ENV: the servers started below inherit from the file, and a
# GITHUB_ENV write would only reach later steps. The update check otherwise pops an async banner in
# the Admin that intercepts clicks and lands in screenshots.
{
  echo "APP_URL=${APP_URL}"
  echo "SHOPWARE_HTTP_CACHE_ENABLED=0"
  echo "SHOPWARE_DISABLE_UPDATE_CHECK=1"
  echo "BLUE_GREEN_DEPLOYMENT=1"
} >> .env.local

if [ "$DEMODATA" = true ]; then
  echo "::group::framework:demodata"
  # --reset-defaults zeroes every count we do not name; without it --products alone still generates
  # the full default set (1000 products, 60 orders, 300 media).
  #
  # Keep --media at 61 or above: only every 30th file becomes a product download, and products ask
  # for up to 3 distinct download media. Fewer aborts the run with "Cannot get 3 elements, only 1 in
  # array" — and only on the rolls that ask for more than exist, so it fails intermittently.
  APP_ENV=prod php bin/console framework:demodata --no-interaction --reset-defaults \
    --products="${DEMODATA_PRODUCTS:-30}" \
    --categories="${DEMODATA_CATEGORIES:-8}" \
    --manufacturers="${DEMODATA_MANUFACTURERS:-5}" \
    --media="${DEMODATA_MEDIA:-90}" \
    --tags="${DEMODATA_TAGS:-10}" \
    --customers="${DEMODATA_CUSTOMERS:-5}" \
    --orders="${DEMODATA_ORDERS:-3}" \
    --properties="${DEMODATA_PROPERTIES:-3}"
  echo "::endgroup::"
fi

echo "::group::index + theme"
php bin/console dal:refresh:index --no-interaction
php bin/console theme:compile --sync
php bin/console cache:clear --no-interaction
echo "::endgroup::"

SYMFONY_DAEMON=1 SYMFONY_NO_TLS=1 SYMFONY_ALLOW_HTTP=1 SYMFONY_ALLOW_ALL_IP=1 symfony server:start --port="$APP_PORT"

# Only 200 counts. Accepting any response lets an unrelated listener pass as the shop, which then
# surfaces as an agent reporting 404 on every path.
for _ in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "${APP_URL}/admin" || echo 000)
  [ "$code" = 200 ] && { ready=1; break; }
  sleep 1
done
if [ "${ready:-0}" != 1 ]; then
  echo "::error::shop did not answer 200 at ${APP_URL}/admin after 60s (last code ${code:-none})"
  diagnose "${APP_URL}/admin"
  exit 1
fi

# Spelled out rather than `command -v socat || { ... }`: a failing install on the right-hand side of
# `||` does not trip set -e, so the run would carry on with no forwarder and the agent would find a
# dead shop.
if ! command -v socat >/dev/null; then
  sudo apt-get update -q
  sudo apt-get install -y -q socat
fi
sudo socat "TCP-LISTEN:${SANDBOX_PORT},fork,reuseaddr" "TCP:127.0.0.1:${APP_PORT}" &

# A browser omits the port for 80, so the Host header — and therefore the sales-channel domain that
# has to match it — carries no port either.
if [ "$SANDBOX_PORT" = 80 ]; then
  SANDBOX_URL="http://${SANDBOX_HOST}"
else
  SANDBOX_URL="http://${SANDBOX_HOST}:${SANDBOX_PORT}"
fi

# Prove the agent's exact path — same port, same Host header — rather than inferring it from the
# localhost probe. This is what turns a sandbox misconfiguration into a failed provisioning step
# instead of a wasted agent run.
for _ in $(seq 1 30); do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 -H "Host: ${SANDBOX_HOST}" "http://127.0.0.1:${SANDBOX_PORT}/admin" || echo 000)
  [ "$code" = 200 ] && { forwarded=1; break; }
  sleep 1
done
if [ "${forwarded:-0}" != 1 ]; then
  echo "::error::shop not reachable as ${SANDBOX_URL}/admin (last code ${code:-none}) — the agent would see a dead shop"
  diagnose "http://127.0.0.1:${SANDBOX_PORT}/admin" "$SANDBOX_HOST"
  exit 1
fi

# SHOP_DIR is deliberately absent from the agent's environment: it has no business touching the
# source tree, and the swap runs host-side. APP_PORT rides along because swap.sh probes the shop on
# it; on its own default it would poll a dead port on any run that moved the shop off 8000.
{
  echo "APP_URL=${SANDBOX_URL}"
  echo "APP_PORT=${APP_PORT}"
  echo "SHOPWARE_ADMIN_USERNAME=admin"
  echo "SHOPWARE_ADMIN_PASSWORD=shopware"
  echo "SW_SHOT_SHOP_DIR=${SHOP_DIR}"
} >> "$GITHUB_ENV"

echo "shop ready at ${APP_URL}, agent reaches it at ${SANDBOX_URL}"
