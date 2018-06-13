<?php declare(strict_types=1);

return [
    'data' => [
        'id' => '548faa1f-7846-436c-8594-4f4aea792d96',
        'type' => 'media',
        'attributes' => [
            'albumId' => '6f51622e-b381-4c75-ae02-63cece27ce72',
            'fileName' => 'teaser.jpg',
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
            'userVersionId' => null,
            'catalogId' => null,
            'tenantId' => null,
        ],
        'links' => [
            'self' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96',
        ],
        'relationships' => [
            'album' => [
                'data' => [
                    'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
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
            'mailAttachments' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f-7846-436c-8594-4f4aea792d96/mail-attachments',
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
        ],
    ],
    'included' => [
        [
            'id' => '6f51622e-b381-4c75-ae02-63cece27ce72',
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
                'self' => '/api/media-album/6f51622e-b381-4c75-ae02-63cece27ce72',
            ],
            'relationships' => [
                'media' => [
                    'links' => [
                        'related' => '/api/media-album/6f51622e-b381-4c75-ae02-63cece27ce72/media',
                    ],
                    'data' => [],
                ],
                'parent' => [
                    'links' => [
                        'related' => '/api/media-album/6f51622e-b381-4c75-ae02-63cece27ce72/parent',
                    ],
                    'data' => null,
                ],
                'children' => [
                    'links' => [
                        'related' => '/api/media-album/6f51622e-b381-4c75-ae02-63cece27ce72/children',
                    ],
                    'data' => [],
                ],
            ]
        ],
    ],
];
