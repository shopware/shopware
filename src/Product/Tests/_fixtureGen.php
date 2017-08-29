#!/usr/bin/env php
<?php

//const MAX_ROWS = 2000;
const MAX_ROWS = 10000;

require __DIR__ . '/../../../vendor/autoload.php';

$faker = Faker\Factory::create();

$genTpl = function(int $i) use($faker): array {
    $ret = [
        'name' => $faker->name(),
        'description' => $faker->text(),
        'descriptionLong' => $faker->randomHtml(2,3),
        'taxUuid' => 'SWAG-CONFIG-TAX-UUID-' . $faker->randomElement([1,4]),
        'productManufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-' . $faker->randomElement(array_merge(range(1, 9), range(11, 17)))],
        'mode' => 0,
        'lastStock' => 1,
        'crossbundlelook' => 1,
        'notification' => 0,
        'template' => $faker->text(),
        'active' => true,

        'categories' => [
            ['categoryUuid' => 'SWAG-CATEGORY-UUID-' . $faker->randomElement([1,3,5,6,8,9,10,11,12,13,14,15,16,17,19,20,34,47,50,54])],
        ],

//            'mainDetail' => [
//                'number' => 'swTEST' . uniqid(),
//                'position' => 0,
//                'prices' => [
//                    [
//                        'customerGroupKey' => 'EK',
//                        'price' => 999,
//                    ],
//                ]
//            ],
        'details' => []
    ];

    $detailCount = 2;rand(1, 10);
    for($i = 0; $i < $detailCount; $i++) {
        $ret['details'][] =             [
            'number' => $faker->uuid(),
            'inStock' => $faker->randomNumber(),
            'position' => 0,
            'additionaltext' => $faker->text(),
            'prices' => [
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
for($i = 0; $i < MAX_ROWS; $i++) {
    if(!($i%100)) {
        echo "$i\n";
    }

    $data[] = $genTpl($i);
}

file_put_contents(__DIR__ . '/_fixtures.php', '<?php return ' . var_export($data, true) . ';');



