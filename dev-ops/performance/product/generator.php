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
        'id' => $faker->uuid,
        'name' => $faker->name(),
        'description' => $faker->text(),
        'descriptionLong' => $faker->randomHtml(2,3),
        'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
        'manufacturer' => [
            'id' => $faker->uuid,
            'name' => $faker->name()
        ],
        'active' => true,
        'categories' => [
            ['categoryId' => \Shopware\Category\Extension\CategoryPathBuilder::ROOT]
        ],
        'stock' => $faker->randomNumber(),
        'prices' => [
            [
                'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 60, 100),
                'quantityStart' => 1,
                'quantityEnd' => 4
            ], [
                'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 40, 59),
                'quantityStart' => 5,
                'quantityEnd' => 10
            ], [
                'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                'price' => $faker->randomFloat(2, 30, 39),
                'quantityStart' => 11,
                'quantityEnd' => 15
            ], [
                'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
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



