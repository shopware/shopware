<?php declare(strict_types=1);

$connection = require __DIR__ . '/boot.php';

$listings = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url WHERE route_name = 'frontend.navigation.page' AND is_deleted = 0");

$details = $connection->fetchFirstColumn("SELECT CONCAT('/', seo_path_info) FROM seo_url  WHERE route_name = 'frontend.detail.page' AND is_deleted = 0");

$numbers = $connection->fetchFirstColumn('SELECT product_number FROM product');

$salutationId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM salutation LIMIT 1');

$countryId = $connection->fetchOne("SELECT LOWER(HEX(country_id)) FROM `country_translation` WHERE `name` = 'Deutschland' LIMIT 1");

if (!$salutationId) {
    throw new RuntimeException('No salutation id found');
}
if (!$countryId) {
    throw new RuntimeException('Country "deutschland" not found');
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

file_put_contents(__DIR__ . '/fixtures/listing_urls.csv', implode(\PHP_EOL, $listings));

file_put_contents(__DIR__ . '/fixtures/product_urls.csv', implode(\PHP_EOL, $details));

file_put_contents(__DIR__ . '/fixtures/register.json', json_encode(['countryId' => $countryId, 'salutationId' => $salutationId], \JSON_THROW_ON_ERROR));

file_put_contents(__DIR__ . '/fixtures/product_numbers.csv', implode(\PHP_EOL, $numbers));
