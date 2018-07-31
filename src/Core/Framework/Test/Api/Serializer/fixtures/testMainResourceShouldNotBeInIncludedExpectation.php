<?php declare(strict_types=1);

return [
    'data' => [
        'id' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
        'type' => 'media_album',
        'attributes' => [
            'name' => 'Manufacturer',
            'position' => 12,
            'parentId' => null,
            'createThumbnails' => true,
            'thumbnailSize' => '200x200',
            'icon' => 'sprite-blue-folder',
            'thumbnailHighDpi' => true,
            'thumbnailQuality' => 90,
            'thumbnailHighDpiQuality' => 60,
            'createdAt' => '2018-01-15T08:01:16+00:00',
            'updatedAt' => null,
            'catalogId' => null,
            'tenantId' => null,
        ],
        'relationships' => [
            'media' => [
                'data' => [
                    ['id' => '3e352be2-d858-46dd-9752-9c0f6b544870', 'type' => 'media'],
                ],
                'links' => [
                    'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/media',
                ],
            ],
            'parent' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/parent',
                ],
            ],
            'children' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/children',
                ],
            ],
            'catalog' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/catalog',
                ],
            ],
        ],
        'links' => [
            'self' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c',
        ],
    ],
    'included' => [
        [
            'id' => '3e352be2-d858-46dd-9752-9c0f6b544870',
            'type' => 'media',
            'attributes' => [
                'albumId' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
                'name' => 'Lagerkorn-5,0klein',
                'userId' => null,
                'mimeType' => 'image/jpg',
                'fileSize' => 18921,
                'metaData' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'description' => null,
                'catalogId' => null,
                'tenantId' => null,
                'extensions' => [
                    'links' => null,
                ]
            ],
            'relationships' => [
                'album' => [
                    'data' => [
                        'id' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
                        'type' => 'media_album',
                    ],
                    'links' => [
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/album',
                    ],
                ],
                'user' => [
                    'data' => null,
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
            ],
            'links' => [
                'self' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870',
            ],
        ],
    ],
];
