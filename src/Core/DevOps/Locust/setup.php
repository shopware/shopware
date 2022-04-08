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
$listings = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url WHERE route_name = 'frontend.navigation.page' AND is_deleted = 0 AND is_canonical = 1" . $limit);

$limit = $env['product_page_limit'] !== null ? ' LIMIT ' . (int) $env['product_page_limit'] : '';
$details = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url  WHERE route_name = 'frontend.detail.page' AND is_deleted = 0 AND is_canonical = 1" . $limit);

$keywords = array_map(static function (string $term) {
    $terms = explode(' ', $term);

    return array_filter($terms, static function (string $split) {
        return mb_strlen($split) >= 4;
    });
}, $connection->fetchFirstColumn('SELECT name FROM product_translation WHERE name IS NOT NULL ' . $limit));

$keywords = array_unique(array_merge(...$keywords));

$products = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, product_number as productNumber FROM product ' . $limit);

$salesChannel = $connection->fetchAssociative(
    'SELECT LOWER(HEX(country_id)) as countryId FROM sales_channel WHERE type_id = :type',
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

if ($salesChannel === false) {
    throw new RuntimeException('No storefront sales channel found');
}

$salesChannel['salutationId'] = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM salutation LIMIT 1');

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

echo 'Collected: ' . count($advertisements) . ' advertisements' . \PHP_EOL;
$fs->dumpFile(__DIR__ . '/fixtures/advertisements.json', json_encode($advertisements, \JSON_THROW_ON_ERROR));
