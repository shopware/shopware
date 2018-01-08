#!/usr/bin/env php
<?php

require_once __DIR__ . '/../Measurement.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__.'/../../../.env');

const FIRST_LEVEL = 20;
const LEVELS = 30;
const LEVEL_COUNT = 40;

$faker = Faker\Factory::create();

$genTpl = function(int $i, ?string $parent = null) use($faker): array {
    return [
        'id' => $faker->id,
        'name' => $faker->name(),
        'parentId' => $parent
    ];
};

$data = [];

for ($i = 1; $i <= FIRST_LEVEL; $i++) {
    $data[] = $first = $genTpl($i, 'SWAG-CATEGORY-ID-3');

    $parent = $first['id'];
    for ($i2 = 1; $i2 <= LEVELS; $i2++) {
        for ($i3 = 1; $i3 <= LEVEL_COUNT; $i3++) {
            $data[] = $category = $genTpl($i2, $parent);
        }
        $parent = $category['id'];
    }
}

file_put_contents(__DIR__ . '/_fixtures.php', '<?php return ' . var_export($data, true) . ';');



