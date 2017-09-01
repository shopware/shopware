#!/usr/bin/env php
<?php

require_once __DIR__ . '/Measurement.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__.'/../.env');

const MAX_ROWS = 10000;

$faker = Faker\Factory::create();

$genTpl = function(int $i) use($faker): array {
    $ret = [
        'name' => $faker->name(),
        'description' => $faker->text(),
        'descriptionLong' => $faker->randomHtml(2,3),
        'taxUuid' => 'SWAG-TAX-UUID-' . $faker->randomElement([1,4]),
        'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-' . $faker->randomElement(array_merge(range(1, 9), range(11, 17)))],
        'mode' => 0,
        'lastStock' => true,
        'crossbundlelook' => 1,
        'notification' => false,
        'template' => $faker->text(),
        'active' => true,

        'categorys' => [
            ['categoryUuid' => 'SWAG-CATEGORY-UUID-' . $faker->randomElement([1,3,5,6,8,9,10,11,12,13,14,15,16,17,19,20,34,47,50,54])],
        ],
        'details' => []
    ];

    $detailCount = 2; //rand(1, 10);
    for($i = 0; $i < $detailCount; $i++) {
        $ret['details'][] =             [
            'number' => $faker->uuid(),
            'inStock' => $faker->randomNumber(),
            'position' => 0,
            'additionaltext' => $faker->text(),
            'productPrices' => [
                [
                    'pricegroup' => $faker->randomElement(['EK', 'H']),
                    'price' => $faker->randomFloat(),
                ],
            ],
        ];

    }

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



