<?php declare(strict_types=1);

return [
    'data' => [
        'id' => 'f343a3c119cf42a7841aa0ac5094908c',
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
            'attributes' => null,
            'avatarId' => null,
        ],
        'links' => [
            'self' => '/api/user/f343a3c119cf42a7841aa0ac5094908c',
        ],
        'relationships' => [
            'media' => [
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/media',
                ],
                'data' => [
                    [
                        'id' => '3e352be2d85846dd97529c0f6b544870',
                        'type' => 'media',
                    ],
                ],
            ],
            'accessKeys' => [
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/access-keys',
                ],
                'data' => [],
            ],
            'locale' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/locale',
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
            'avatarMedia' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/f343a3c119cf42a7841aa0ac5094908c/avatar-media',
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
                'title' => 'Lagerkorn-5,0klein',
                'alt' => null,
                'userId' => 'f343a3c119cf42a7841aa0ac5094908c',
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 18921,
                'metaData' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'url' => '',
                'hasFile' => false,
                'fileName' => null,
                'mediaType' => null,
                'uploadedAt' => null,
                'mediaFolderId' => null,
                'attributes' => null,
            ],
            'meta' => null,
            'relationships' => [
                'translations' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/translations',
                    ],
                ],
                'user' => [
                    'data' => [
                        'id' => 'f343a3c119cf42a7841aa0ac5094908c',
                        'type' => 'user',
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
                'avatarUser' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/3e352be2d85846dd97529c0f6b544870/avatar-user',
                    ],
                ],
            ],
            'links' => [
                'self' => '/api/media/3e352be2d85846dd97529c0f6b544870',
            ],
        ],
    ],
];
