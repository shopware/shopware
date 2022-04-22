<?php declare(strict_types=1);

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

$connection = require __DIR__ . '/boot.php';

$fs = new Filesystem();

$env = json_decode((string) file_get_contents(__DIR__ . '/env.dist.json'), true, 512, \JSON_THROW_ON_ERROR);
if (file_exists(__DIR__ . '/env.json')) {
    $env = array_replace_recursive($env, json_decode((string) file_get_contents(__DIR__ . '/env.json'), true, 512, \JSON_THROW_ON_ERROR));
}

/** @var \Doctrine\DBAL\Connection $connection */
$limit = $env['category_page_limit'] !== null ? ' LIMIT ' . (int) $env['category_page_limit'] : '';

$ids = $connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM category WHERE level <= 3 ' . $limit);

$listings = $connection->fetchFirstColumn(
    "SELECT CONCAT('/', seo_path_info) FROM seo_url WHERE route_name = 'frontend.navigation.page' AND is_deleted = 0 AND is_canonical = 1 AND foreign_key IN (:ids)",
    ['ids' => Uuid::fromHexToBytesList($ids)],
    ['ids' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
);

$storeApiCategories = $connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM category WHERE level <= 5 ' . $limit);

$limit = $env['product_page_limit'] !== null ? ' LIMIT ' . (int) $env['product_page_limit'] : '';
$details = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url  WHERE route_name = 'frontend.detail.page' AND is_deleted = 0 AND is_canonical = 1" . $limit);

$keywords = array_map(static function (string $term) {
    $terms = explode(' ', $term);

    return array_filter($terms, static function (string $split) {
        return mb_strlen($split) >= 4;
    });
}, $connection->fetchFirstColumn('SELECT name FROM product_translation WHERE name IS NOT NULL ' . $limit));

$keywords = array_values(array_unique(array_merge(...$keywords)));

$products = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, product_number as productNumber FROM product ' . $limit);

$properties = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM property_group_option LIMIT 300');

$media = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as mediaId FROM media LIMIT 100');

$categories = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM category WHERE child_count <= 1 LIMIT 100');

$taxId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM tax LIMIT 1');

$salesChannel = $connection->fetchAssociative(
    'SELECT LOWER(HEX(country_id)) as countryId, access_key, LOWER(HEX(id)) as id FROM sales_channel WHERE type_id = :type LIMIT 1',
    ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
);

$advertisements = $connection->fetchAllAssociative(
    "
    SELECT product_number as number, CONCAT('/', seo_path_info) as url
    FROM product
       INNER JOIN seo_url
          ON seo_url.route_name = 'frontend.detail.page' AND is_deleted = 0 AND is_canonical = 1
    WHERE is_closeout = 0 AND min_purchase = 1
    LIMIT " . (int) $env['advertisements']
);

$connection->executeStatement(
    'UPDATE sales_channel SET footer_category_version_id = :version, footer_category_id = (SELECT id FROM category WHERE child_count > 0 AND parent_id IS NOT NULL ORDER BY child_count DESC LIMIT 1) WHERE footer_category_id IS NULL',
    ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
);

$connection->executeStatement(
    'UPDATE sales_channel SET service_category_version_id = :version, service_category_id = (SELECT id FROM category WHERE child_count > 0 AND parent_id IS NOT NULL ORDER BY child_count DESC LIMIT 1) WHERE service_category_id IS NULL',
    ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
);

if ($salesChannel === false) {
    throw new RuntimeException('No storefront sales channel found');
}

$salesChannel['salutationId'] = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM salutation LIMIT 1');

$salesChannel['domain'] = $connection->fetchOne('SELECT url FROM sales_channel_domain WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($salesChannel['id'])]);

$salesChannel['currencies'] = $connection->fetchFirstColumn('SELECT LOWER(HEX(currency_id)) FROM sales_channel_currency WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($salesChannel['id'])]);

$salesChannel['languages'] = $connection->fetchFirstColumn('SELECT LOWER(HEX(language_id)) FROM sales_channel_language WHERE sales_channel_id = :id', ['id' => Uuid::fromHexToBytes($salesChannel['id'])]);

if (empty($details)) {
    throw new RuntimeException('No product urls found');
}
if (empty($listings)) {
    throw new RuntimeException('No listing urls found');
}
if (empty($products)) {
    throw new RuntimeException('No product numbers found');
}

echo 'Collected: ' . count($listings) . ' listing urls' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/listing_urls.json', json_encode($listings, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($details) . ' product urls' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/product_urls.json', json_encode($details, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($salesChannel) . ' sales channels' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/sales_channel.json', json_encode($salesChannel, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($keywords) . ' keywords' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/keywords.json', json_encode($keywords, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($products) . ' products' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/products.json', json_encode($products, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($storeApiCategories) . ' categories for store-api' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/categories.json', json_encode($storeApiCategories, \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($categories) . ' categories' . \PHP_EOL;
echo 'Collected: ' . count($properties) . ' properties' . \PHP_EOL;
echo 'Collected: ' . count($media) . ' media' . \PHP_EOL;

$fs->dumpFile(__DIR__ . '/fixtures/imports.json', json_encode([
    'categories' => $categories,
    'media' => $media,
    'properties' => $properties,
    'salesChannelId' => $salesChannel['id'],
    'taxId' => $taxId,
], \JSON_THROW_ON_ERROR));

echo 'Collected: ' . count($advertisements) . ' advertisements' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/advertisements.json', json_encode($advertisements, \JSON_THROW_ON_ERROR));
