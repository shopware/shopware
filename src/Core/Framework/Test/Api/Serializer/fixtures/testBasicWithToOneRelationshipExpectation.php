<?php declare(strict_types=1);

return [
    'data' => [
        'id' => '548faa1f7846436c85944f4aea792d96',
        'type' => 'media',
        'attributes' => [
            'userId' => '6f51622eb3814c75ae0263cece27ce72',
            'mediaFolderId' => null,
            'mimeType' => 'image/jpg',
            'fileExtension' => 'jpg',
            'uploadedAt' => null,
            'fileName' => null,
            'fileSize' => 93889,
            'metaData' => null,
            'mediaType' => null,
            'createdAt' => '2012-08-31T00:00:00+00:00',
            'updatedAt' => '2017-11-21T11:25:34+00:00',
            'alt' => null,
            'title' => '2',
            'url' => '',
            'attributes' => null,
            'hasFile' => false,
        ],
        'links' => [
            'self' => '/api/media/548faa1f7846436c85944f4aea792d96',
        ],
        'relationships' => [
            'user' => [
                'data' => [
                    'type' => 'user',
                    'id' => '6f51622eb3814c75ae0263cece27ce72',
                ],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/user',
                ],
            ],
            'categories' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/categories',
                ],
            ],
            'productManufacturers' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/product-manufacturers',
                ],
            ],
            'productMedia' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/product-media',
                ],
            ],
            'avatarUser' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/avatar-user',
                ],
            ],
            'translations' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/translations',
                ],
            ],
            'thumbnails' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/thumbnails',
                ],
            ],
            'mediaFolder' => [
                'data' => null,
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/media-folder',
                ],
            ],
            'configurationGroupOptions' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/configuration-group-options',
                ],
            ],
            'tags' => [
                'data' => [],
                'links' => [
                    'related' => '/api/media/548faa1f7846436c85944f4aea792d96/tags',
                ],
            ],
        ],
        'meta' => null,
    ],
    'included' => [
        [
            'id' => '6f51622eb3814c75ae0263cece27ce72',
            'type' => 'user',
            'attributes' => [
                'localeId' => null,
                'avatarId' => null,
                'username' => 'user1',
                'password' => 'password',
                'name' => 'Manufacturer',
                'email' => 'user1@shop.de',
                'lastLogin' => '2018-01-15T08:01:16+00:00',
                'active' => true,
                'failedLogins' => 0,
                'lockedUntil' => null,
                'attributes' => null,
                'createdAt' => '2018-01-15T08:01:16+00:00',
                'updatedAt' => null,
            ],
            'links' => [
                'self' => '/api/user/6f51622eb3814c75ae0263cece27ce72',
            ],
            'relationships' => [
                'locale' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/locale',
                    ],
                ],
                'avatarMedia' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/avatar-media',
                    ],
                ],
                'media' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/media',
                    ],
                ],
                'accessKeys' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/access-keys',
                    ],
                ],
                'stateMachineHistoryEntries' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/state-machine-history-entries',
                    ],
                ],
                'recoveryUser' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/recovery-user',
                    ],
                ],
            ],
            'meta' => null,
        ],
    ],
];
