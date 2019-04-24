<?php declare(strict_types=1);

return [
    'data' => [
        [
            'id' => '3e352be2d85846dd97529c0f6b544870',
            'type' => 'media_folder',
            'attributes' => [
                'useParentConfiguration' => false,
                'configurationId' => null,
                'defaultFolderId' => null,
                'parentId' => null,
                'childCount' => 1,
                'name' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'customFields' => null,
            ],
            'links' => [
                'self' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870',
            ],
            'relationships' => [
                'parent' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870/parent',
                    ],
                ],
                'children' => [
                    'data' => [
                        0 => [
                            'type' => 'media_folder',
                            'id' => '5846dd97529c0f6b5448713e352be2d8',
                        ],
                    ],
                    'links' => [
                        'related' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870/children',
                    ],
                ],
                'media' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870/media',
                    ],
                ],
                'defaultFolder' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870/default-folder',
                    ],
                ],
                'configuration' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/3e352be2d85846dd97529c0f6b544870/configuration',
                    ],
                ],
            ],
            'meta' => null,
        ],
    ],
    'included' => [
        0 => [
            'id' => '5846dd97529c0f6b5448713e352be2d8',
            'type' => 'media_folder',
            'attributes' => [
                'useParentConfiguration' => true,
                'configurationId' => null,
                'defaultFolderId' => null,
                'parentId' => '3e352be2d85846dd97529c0f6b544870',
                'childCount' => 1,
                'name' => null,
                'createdAt' => '2012-08-15T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'customFields' => null,
            ],
            'links' => [
                'self' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8',
            ],
            'relationships' => [
                'parent' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8/parent',
                    ],
                ],
                'children' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8/children',
                    ],
                ],
                'media' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8/media',
                    ],
                ],
                'defaultFolder' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8/default-folder',
                    ],
                ],
                'configuration' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media-folder/5846dd97529c0f6b5448713e352be2d8/configuration',
                    ],
                ],
            ],
            'meta' => null,
        ],
    ],
];
