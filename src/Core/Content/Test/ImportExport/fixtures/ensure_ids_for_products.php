<?php declare(strict_types=1);

use Shopware\Core\Framework\Uuid\Uuid;

if (!function_exists('convertToUuid')) {
    function convertToUuid($name): string
    {
        return Uuid::fromStringToHex($name);
    }
}

$expected = [
    'id' => convertToUuid('product1'),
    'categories' => [
        [
            'id' => convertToUuid('category1'),
        ],
        [
            'id' => convertToUuid('category2'),
        ],
    ],
    'tax' => [
        'id' => convertToUuid('tax1'),
    ],
    'cover' => [
        'id' => convertToUuid('cover1'),
    ],
    'manufacturer' => [
        'id' => convertToUuid('manufacturer1'),
    ],
    'properties' => [
        [
            'id' => convertToUuid('property1'),
        ],
        [
            'id' => convertToUuid('property2'),
        ],
    ],
    'options' => [
        [
            'id' => convertToUuid('property1'),
        ],
        [
            'id' => convertToUuid('property2'),
        ],
    ],
];

$import = [
    'id' => 'product1',
    'categories' => 'category1|category2',
    'tax' => [
        'id' => 'tax1',
    ],
    'cover' => [
        'id' => 'cover1',
    ],
    'manufacturer' => [
        'id' => 'manufacturer1',
    ],
    'properties' => 'property1|property2',
    'options' => 'property1|property2',
];

return [$expected, $import];
