<?php declare(strict_types=1);

return [
    'data' => [
        [
            'id' => '3e352be2-d858-46dd-9752-9c0f6b544870',
            'type' => 'media',
            'attributes' => [
                'albumId' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
                'fileName' => 'Lagerkorn-50klein.jpg',
                'mimeType' => 'image/jpg',
                'fileSize' => 18921,
                'metaData' => null,
                'userId' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'name' => 'Lagerkorn-5,0klein',
                'description' => null,
                'versionId' => null,
                'mediaAlbumVersionId' => null,
                'catalogId' => null,
                'tenantId' => null,
            ],
            'links' => [
                'self' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870',
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
        ],
        [
            'id' => 'f1ad1d0c-0245-4a40-abf2-50f764d16248',
            'type' => 'media',
            'attributes' => [
                'albumId' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
                'fileName' => 'Jasmine-Lotus-Cover.jpg',
                'mimeType' => 'image/jpg',
                'fileSize' => 155633,
                'metaData' => null,
                'userId' => null,
                'createdAt' => '2012-08-17T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'name' => 'Jasmine-Lotus-Cover',
                'description' => null,
                'versionId' => null,
                'mediaAlbumVersionId' => null,
                'catalogId' => null,
                'tenantId' => null,
            ],
            'relationships' => [
                'album' => [
                    'data' => [
                        'id' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
                        'type' => 'media_album',
                    ],
                    'links' => [
                        'related' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248/album',
                    ],
                ],
                'user' => [
                    'data' => null,
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
            ],
            'links' => [
                'self' => '/api/media/f1ad1d0c-0245-4a40-abf2-50f764d16248',
            ],
        ],
    ],
    'included' => [
        [
            'id' => 'f343a3c1-19cf-42a7-841a-a0ac5094908c',
            'type' => 'media_album',
            'attributes' => [
                'parentId' => null,
                'position' => 12,
                'createThumbnails' => true,
                'thumbnailSize' => '200x200',
                'icon' => 'sprite-blue-folder',
                'thumbnailHighDpi' => true,
                'thumbnailQuality' => 90,
                'thumbnailHighDpiQuality' => 60,
                'createdAt' => '2018-01-15T08:01:16+00:00',
                'updatedAt' => null,
                'name' => 'Manufacturer',
                'versionId' => null,
                'parentVersionId' => null,
                'catalogId' => null,
                'tenantId' => null,
            ],
            'links' => [
                'self' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c',
            ],
            'relationships' => [
                'media' => [
                    'links' => [
                        'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/media',
                    ],
                    'data' => [],
                ],
                'parent' => [
                    'links' => [
                        'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/parent',
                    ],
                    'data' => null,
                ],
                'children' => [
                    'links' => [
                        'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/children',
                    ],
                    'data' => [],
                ],
                'catalog' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-album/f343a3c1-19cf-42a7-841a-a0ac5094908c/catalog',
                    ],
                ],
            ],
        ],
    ],
];
