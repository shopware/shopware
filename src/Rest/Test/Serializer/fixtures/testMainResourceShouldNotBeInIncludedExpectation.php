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
            'versionId' => null,
            'parentVersionId' => null,
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
                'fileName' => 'Lagerkorn-50klein.jpg',
                'mimeType' => 'image/jpg',
                'fileSize' => 18921,
                'metaData' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'description' => null,
                'versionId' => null,
                'mediaAlbumVersionId' => null,
                'userVersionId' => null,
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
                        'related' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870/album',
                    ],
                ],
            ],
            'links' => [
                'self' => '/api/media/3e352be2-d858-46dd-9752-9c0f6b544870',
            ],
        ],
    ],
];
