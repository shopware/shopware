<?php declare(strict_types=1);

return [
    'data' => [
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
                'data' => [
                    [
                        'id' => '548faa1f-7846-436c-8594-4f4aea792d96',
                        'type' => 'media',
                    ],
                ],
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
    'included' => [
        [
            'id' => '548faa1f-7846-436c-8594-4f4aea792d96',
            'type' => 'media',
            'attributes' => [
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 93889,
                'metaData' => null,
                'userId' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                'createdAt' => '2012-08-31T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'title' => '2',
                'description' => null,
                'url' => '',
                'hasFile' => false,
                'fileName' => null,
                'mediaType' => null,
            ],
            'links' => [
                'self' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96',
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
                        'type' => 'user',
                    ],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/user',
                    ],
                ],
                'categories' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/categories',
                    ],
                ],
                'productManufacturers' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/product-manufacturers',
                    ],
                ],
                'productMedia' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/product-media',
                    ],
                ],
                'thumbnails' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/thumbnails',
                    ],
                ],
            ],
        ],
    ],
];
