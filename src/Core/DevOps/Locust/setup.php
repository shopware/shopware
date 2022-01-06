<?php

/** @var \Doctrine\DBAL\Connection $connection */

use Shopware\Core\Defaults;

$connection = require  __DIR__ . '/boot.php';

$listings = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url WHERE route_name = 'frontend.navigation.page' AND is_deleted = 0");

$details = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url  WHERE route_name = 'frontend.detail.page' AND is_deleted = 0");

$keywords = $connection->fetchFirstColumn("SELECT keyword FROM  product_keyword_dictionary LIMIT 5000");

$numbers = $connection->fetchFirstColumn('SELECT product_number FROM product');

$salutationId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM salutation LIMIT 1');

$countryId = $connection->fetchOne("SELECT LOWER(HEX(country_id)) FROM `country_translation` WHERE `name` = 'Deutschland' LIMIT 1");

$productIds = $connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM product LIMIT 5000');

$currencyId = Defaults::CURRENCY;

$categoryIds = $connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM category LIMIT 2000');

$salesChannelId = $connection->fetchOne("SELECT LOWER(HEX(sales_channel_id)) FROM sales_channel_translation WHERE name = 'Storefront' LIMIT 1");

$taxId = $connection->fetchOne("SELECT LOWER(HEX(id)) FROM tax LIMIT 1");

if (!$salutationId) {
    throw new RuntimeException('No salutation id found');
}
if (!$countryId) {
    throw new RuntimeException('Country "deutschland" not found');
}
if (empty($keywords)) {
    throw new RuntimeException('No search keywords found');
}
if (empty($details)) {
    throw new RuntimeException('No product urls found');
}
if (empty($listings)) {
    throw new RuntimeException('No listing urls found');
}
if (empty($numbers)) {
    throw new RuntimeException('No product numbers found');
}
if (empty($categoryIds)) {
    throw new RuntimeException('No category ids found');
}
if (empty($productIds)) {
    throw new RuntimeException('No product ids found');
}
if (empty($salesChannelId)) {
    throw new RuntimeException("Sales channel with name 'Storefront' not found");
}

file_put_contents(__DIR__ . '/fixtures/listing_urls.csv', implode(PHP_EOL, $listings));

file_put_contents(__DIR__ . '/fixtures/product_urls.csv', implode(PHP_EOL, $details));

file_put_contents(__DIR__ . '/fixtures/keywords.csv', implode(PHP_EOL, $keywords));

file_put_contents(__DIR__ . '/fixtures/register.json', json_encode(['countryId' => $countryId, 'salutationId' => $salutationId], JSON_THROW_ON_ERROR));

file_put_contents(__DIR__ . '/fixtures/product_numbers.csv', implode(PHP_EOL, $numbers));

file_put_contents(__DIR__ . '/fixtures/importer.json', json_encode([
    'currencyId' => $currencyId,
    'taxId' => $taxId,
    'salesChannelId' => $salesChannelId,
    'productIds' => $productIds,
    'categoryIds' => $categoryIds
], JSON_THROW_ON_ERROR));
