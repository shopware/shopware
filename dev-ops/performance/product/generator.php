#!/usr/bin/env php
<?php

require_once __DIR__ . '/../Measurement.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__.'/../../../.env');

const MAX_ROWS = 15000;

$faker = Faker\Factory::create();

$genTpl = function(int $i) use($faker): array {
    $ret = [
        'uuid' => $faker->uuid(),
        'name' => $faker->name(),
        'description' => $faker->text(),
        'descriptionLong' => $faker->randomHtml(2,3),
        'taxUuid' => 'SWAG-TAX-UUID-' . $faker->randomElement([1,4]),
        'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-' . $faker->randomElement(array_merge(range(1, 9), range(11, 17)))],
        'active' => true,
        'categories' => [
            ['categoryUuid' => 'SWAG-CATEGORY-UUID-' . $faker->randomElement([34,47,50,54])],
            ['categoryUuid' => 'SWAG-CATEGORY-UUID-' . $faker->randomElement([16,17,19,20])],
            ['categoryUuid' => 'SWAG-CATEGORY-UUID-' . $faker->randomElement([13,14,15])]
        ],
        'stock' => $faker->randomNumber(),
        'prices' => [
            [
                'customerGroupUuid' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 60, 100),
                'quantityStart' => 1,
                'quantityEnd' => 4
            ], [
                'customerGroupUuid' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 40, 59),
                'quantityStart' => 5,
                'quantityEnd' => 10
            ], [
                'customerGroupUuid' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 30, 39),
                'quantityStart' => 11,
                'quantityEnd' => 15
            ], [
                'customerGroupUuid' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 10, 29),
                'quantityStart' => 16
            ],
            [
                'customerGroupUuid' => 'SWAG-CUSTOMER-GROUP-UUID-2',
                'price' => $faker->randomFloat(2, 60, 100),
                'quantityStart' => 1,
                'quantityEnd' => 4
            ], [
                'customerGroupUuid' => 'SWAG-CUSTOMER-GROUP-UUID-2',
                'price' => $faker->randomFloat(2, 40, 59),
                'quantityStart' => 5,
                'quantityEnd' => 10
            ], [
                'customerGroupUuid' => 'SWAG-CUSTOMER-GROUP-UUID-2',
                'price' => $faker->randomFloat(2, 30, 39),
                'quantityStart' => 11,
                'quantityEnd' => 15
            ], [
                'customerGroupUuid' => 'SWAG-CUSTOMER-GROUP-UUID-2',
                'price' => $faker->randomFloat(2, 10, 29),
                'quantityStart' => 16
            ]
        ]
    ];

    return $ret;
};

$data = [];
for($i = 1; $i <= MAX_ROWS; $i++) {
    if(!($i%100)) {
        echo "$i\n";
    }

    $data[$i] = $genTpl($i);
}

file_put_contents(__DIR__ . '/_fixtures.php', '<?php return ' . var_export($data, true) . ';');



