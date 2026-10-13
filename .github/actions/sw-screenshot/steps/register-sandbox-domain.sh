#!/usr/bin/env bash
# Register the sandbox-facing URL as an additional storefront sales-channel domain.
#
# The agent reaches the shop as host.docker.internal, and Shopware resolves the sales channel from
# the request Host — an unregistered host makes the storefront reject the request before rendering.
# The entry is additive, so host-side steps keep using the localhost domain.
#
# Env: SHOP_DIR (required), SANDBOX_URL (required).
set -euo pipefail

: "${SHOP_DIR:?SHOP_DIR is required}"
: "${SANDBOX_URL:?SANDBOX_URL is required}"

cd "$SHOP_DIR"

php -r '
require __DIR__ . "/vendor/autoload.php";

$url = getenv("SANDBOX_URL");
$parts = parse_url(getenv("DATABASE_URL"));
$pdo = new PDO(
    sprintf("mysql:host=%s;port=%d;dbname=%s", $parts["host"] ?? "127.0.0.1", $parts["port"] ?? 3306, ltrim($parts["path"] ?? "", "/")),
    rawurldecode($parts["user"] ?? "root"),
    rawurldecode($parts["pass"] ?? "")
);

$existing = $pdo->query("SELECT id FROM sales_channel_domain WHERE url = " . $pdo->quote($url))->fetchColumn();
if ($existing !== false) {
    echo "already registered: $url\n";
    exit(0);
}

// Clone an existing storefront domain so language, currency and snippet set match the domain the
// install already created; only the id and the url differ. The column list comes from
// information_schema rather than SELECT *, because MySQL rejects any write to a generated column
// (MariaDB tolerates it, so a local test on MariaDB will not catch this).
$columns = $pdo->query("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = \"sales_channel_domain\"
      AND EXTRA NOT LIKE \"%GENERATED%\"
")->fetchAll(PDO::FETCH_COLUMN);

$quoted = implode(", ", array_map(static fn ($c) => "d.`$c`", $columns));

$row = $pdo->query("
    SELECT $quoted FROM sales_channel_domain d
    INNER JOIN sales_channel c ON c.id = d.sales_channel_id
    WHERE c.active = 1 AND c.type_id = UNHEX(\"8a243080f92e4c719546314b577cf82b\")
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "no storefront sales-channel domain to clone\n");
    exit(1);
}

$row["id"] = random_bytes(16);
$row["url"] = $url;
$row["created_at"] = date("Y-m-d H:i:s");
$row["updated_at"] = null;

$names = array_keys($row);
$statement = $pdo->prepare(sprintf(
    "INSERT INTO sales_channel_domain (%s) VALUES (%s)",
    implode(", ", array_map(static fn ($c) => "`$c`", $names)),
    implode(", ", array_fill(0, count($names), "?"))
));
$statement->execute(array_values($row));

echo "registered: $url\n";
'

php bin/console cache:clear --no-interaction >/dev/null
