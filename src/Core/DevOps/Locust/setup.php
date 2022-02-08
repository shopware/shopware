<?php declare(strict_types=1);

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

$connection = require __DIR__ . '/boot.php';

/** @var \Doctrine\DBAL\Connection $connection */
$listings = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url WHERE route_name = 'frontend.navigation.page' AND is_deleted = 0 AND is_canonical = 1");

$details = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url  WHERE route_name = 'frontend.detail.page' AND is_deleted = 0 AND is_canonical = 1");

$keywords = $connection->fetchFirstColumn('SELECT keyword FROM product_keyword_dictionary');

$products = $connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, product_number as productNumber FROM product');

$salesChannel = $connection->fetchAssociative(
    'SELECT LOWER(HEX(country_id)) as countryId FROM sales_channel WHERE type_id = :type',
    [':type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
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
file_put_contents(__DIR__ . '/fixtures/listing_urls.json', json_encode($listings));

echo 'Collected: ' . count($details) . ' product urls' . \PHP_EOL;
file_put_contents(__DIR__ . '/fixtures/product_urls.json', json_encode($details));

file_put_contents(__DIR__ . '/fixtures/sales_channel.json', json_encode($salesChannel));

echo 'Collected: ' . count($keywords) . ' keywords' . \PHP_EOL;
file_put_contents(__DIR__ . '/fixtures/keywords.json', json_encode($keywords));

echo 'Collected: ' . count($products) . ' products' . \PHP_EOL;
file_put_contents(__DIR__ . '/fixtures/products.json', json_encode($products));
