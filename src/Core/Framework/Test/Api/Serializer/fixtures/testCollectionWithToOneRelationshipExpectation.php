<?php declare(strict_types=1);

return [
    'data' => [
        [
            'id' => '3e352be2-d858-46dd-9752-9c0f6b544870',
            'type' => 'media',
            'attributes' => [
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 18921,
                'metaData' => null,
                'userId' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'name' => 'Lagerkorn-5,0klein',
                'description' => null,
                'catalogId' => null,
                'url' => '',
                'hasFile' => true,
                'fileName' => null,
            ],
            'links' => [
                'self' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870',
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                        'type' => 'user',
                    ],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/user',
                    ],
                ],
                'categories' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/categories',
                    ],
                ],
                'productManufacturers' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/product-manufacturers',
                    ],
                ],
                'productMedia' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/product-media',
                    ],
                ],
                'catalog' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/catalog',
                    ],
                ],
                'thumbnails' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/thumbnails',
                    ],
                ],
            ],
        ],
        [
            'id' => 'f1ad1d0c-0245-4a40-abf2-50f764d16248',
            'type' => 'media',
            'attributes' => [
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 155633,
                'metaData' => null,
                'userId' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                'createdAt' => '2012-08-17T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'name' => 'Jasmine-Lotus-Cover',
                'description' => null,
                'catalogId' => null,
                'url' => '',
                'hasFile' => true,
                'fileName' => null,
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                        'type' => 'user',
                    ],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/user',
                    ],
                ],
                'categories' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/categories',
                    ],
                ],
                'productManufacturers' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/product-manufacturers',
                    ],
                ],
                'productMedia' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/product-media',
                    ],
                ],
                'catalog' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/catalog',
                    ],
                ],
                'thumbnails' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/thumbnails',
                    ],
                ],
            ],
            'links' => [
                'self' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248',
            ],
        ],
    ],
    'included' => [
        [
            'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
            'type' => 'user',
            'attributes' => [
                'localeId' => null,
                'username' => 'user1',
                'password' => 'password',
                'email' => 'user1@shop.de',
                'lastLogin' => '2018-01-15T08:01:16+00:00',
                'active' => true,
                'failedLogins' => 0,
                'lockedUntil' => null,
                'name' => 'Manufacturer',
                'createdAt' => '2018-01-15T08:01:16+00:00',
                'updatedAt' => null,
            ],
            'links' => [
                'self' => '/api/user/6f51622e-b381-4c75-ae02-63cece27ce72',
            ],
            'relationships' => [
                'media' => [
                    'links' => [
                        'related' => '/api/user/6f51622e-b381-4c75-ae02-63cece27ce72/media',
                    ],
                    'data' => [],
                ],
                'accessKeys' => [
                    'links' => [
                        'related' => '/api/user/6f51622e-b381-4c75-ae02-63cece27ce72/access-keys',
                    ],
                    'data' => [],
                ],
                'locale' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/user/6f51622e-b381-4c75-ae02-63cece27ce72/locale',
                    ],
                ],
            ],
        ],
    ],
];
