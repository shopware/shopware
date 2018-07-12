<?php declare(strict_types=1);

return [
    'data' => [
        'id' => 'c83a7721-270a-4add-82fd-e60b1dd0c47e',
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
            'self' => '/api/media-album/c83a7721-270a-4add-82fd-e60b1dd0c47e',
        ],
        'relationships' => [
            'media' => [
                'data' => [
                    ['id' => '548faa1f-7846-436c-8594-4f4aea792d96', 'type' => 'media'],
                ],
                'links' => [
                    'related' => '/api/media-album/c83a7721-270a-4add-82fd-e60b1dd0c47e/media',
                ],
            ],
            'parent' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media-album/c83a7721-270a-4add-82fd-e60b1dd0c47e/parent',
                ],
            ],
            'children' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media-album/c83a7721-270a-4add-82fd-e60b1dd0c47e/children',
                ],
            ],
            'catalog' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media-album/c83a7721-270a-4add-82fd-e60b1dd0c47e/catalog',
                ],
            ],
        ],
    ],
    'included' => [
        [
            'id' => '548faa1f-7846-436c-8594-4f4aea792d96',
            'type' => 'media',
            'attributes' => [
                'albumId' => 'c83a7721-270a-4add-82fd-e60b1dd0c47e',
                'mimeType' => 'image/jpg',
                'fileSize' => 93889,
                'metaData' => null,
                'userId' => null,
                'createdAt' => '2012-08-31T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'name' => '2',
                'description' => null,
                'versionId' => null,
                'mediaAlbumVersionId' => null,
                'catalogId' => null,
                'tenantId' => null,
            ],
            'links' => [
                'self' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96',
            ],
            'relationships' => [
                'album' => [
                    'data' => [
                        'id' => 'c83a7721-270a-4add-82fd-e60b1dd0c47e',
                        'type' => 'media_album',
                    ],
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/album',
                    ],
                ],
                'user' => [
                    'data' => null,
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
                'catalog' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/catalog',
                    ],
                ],
            ],
        ],
    ],
];
