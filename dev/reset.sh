#!/usr/bin/env bash
# Rebuilds the local Kimai from scratch: fresh database, plugin installed, sample data.
# Usage: dev/reset.sh
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f dev/compose.yaml"
CONSOLE="$COMPOSE exec -T kimai /opt/kimai/bin/console"
PLUGIN=/opt/kimai/var/plugins/DrehzettelBundle

$COMPOSE down -v
$COMPOSE up -d
until [ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8001/en/login)" = 200 ]; do sleep 3; done

$CONSOLE kimai:bundle:drehzettel:install
$COMPOSE exec -T kimai php "$PLUGIN/dev/seed.php"
$CONSOLE kimai:user:create crew2 crew2@example.test ROLE_USER crew2-dev-pass
echo "Ready: http://localhost:8001  admin@example.test / admin-dev-pass"
