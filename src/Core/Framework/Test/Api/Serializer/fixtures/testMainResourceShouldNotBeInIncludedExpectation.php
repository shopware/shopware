<?php declare(strict_types=1);

return [
    'data' => [
        'id' => 'f343a3c119cf42a7841aa0ac5094908c',
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
            'self' => '/api/user/f343a3c119cf42a7841aa0ac5094908c',
        ],
        'relationships' => [
            'locale' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/locale',
                ],
            ],
            'avatarMedia' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/avatar-media',
                ],
            ],
            'media' => [
                'data' => [
                    [
                        'type' => 'media',
                        'id' => '3e352be2d85846dd97529c0f6b544870',
                    ],
                ],
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/media',
                ],
            ],
            'accessKeys' => [
                'data' => [],
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/access-keys',
                ],
            ],
            'stateMachineHistoryEntries' => [
                'data' => [],
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/state-machine-history-entries',
                ],
            ],
            'recoveryUser' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/recovery-user',
                ],
            ],
        ],
        'meta' => null,
    ],
    'included' => [
        [
            'id' => '3e352be2d85846dd97529c0f6b544870',
            'type' => 'media',
            'attributes' => [
                'userId' => 'f343a3c119cf42a7841aa0ac5094908c',
                'mediaFolderId' => null,
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'uploadedAt' => null,
                'fileName' => null,
                'fileSize' => 18921,
                'metaData' => null,
                'mediaType' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'alt' => null,
                'title' => 'Lagerkorn-5,0klein',
                'url' => '',
                'attributes' => null,
                'hasFile' => false,
            ],
            'links' => [
                'self' => '/api/media/3e352be2d85846dd97529c0f6b544870',
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'user',
                        'id' => 'f343a3c119cf42a7841aa0ac5094908c',
                    ],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/user',
                    ],
                ],
                'categories' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/categories',
                    ],
                ],
                'productManufacturers' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/product-manufacturers',
                    ],
                ],
                'productMedia' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/product-media',
                    ],
                ],
                'avatarUser' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/avatar-user',
                    ],
                ],
                'translations' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/translations',
                    ],
                ],
                'thumbnails' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/thumbnails',
                    ],
                ],
                'mediaFolder' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/media-folder',
                    ],
                ],
                'configurationGroupOptions' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/configuration-group-options',
                    ],
                ],
                'tags' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/tags',
                    ],
                ],
            ],
            'meta' => null,
        ],
    ],
];
