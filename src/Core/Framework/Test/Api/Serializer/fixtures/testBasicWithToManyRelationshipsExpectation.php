<?php declare(strict_types=1);

return [
    'data' => [
        'id' => '6f51622eb3814c75ae0263cece27ce72',
        'type' => 'user',
        'attributes' => [
            'localeId' => null,
            'username' => 'user1',
            'password' => 'password',
            'email' => 'user1@shop.de',
            'active' => true,
            'firstName' => 'Manufacturer',
            'lastName' => '',
            'attributes' => null,
            'createdAt' => '2018-01-15T08:01:16+00:00',
            'updatedAt' => null,
            'avatarId' => null,
        ],
        'links' => [
            'self' => '/api/user/6f51622eb3814c75ae0263cece27ce72',
        ],
        'relationships' => [
            'media' => [
                'links' => [
                    'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/media',
                ],
                'data' => [
                    [
                        'id' => '548faa1f7846436c85944f4aea792d96',
                        'type' => 'media',
                    ],
                ],
            ],
            'accessKeys' => [
                'links' => [
                    'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/access-keys',
                ],
                'data' => [],
            ],
            'locale' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/locale',
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
            'avatarMedia' => [
                'data' => null,
                'links' => [
                    'related' => '/api/user/6f51622eb3814c75ae0263cece27ce72/avatar-media',
                ],
            ],
        ],
        'meta' => null,
    ],
    'included' => [
        [
            'id' => '548faa1f7846436c85944f4aea792d96',
            'type' => 'media',
            'attributes' => [
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 93889,
                'metaData' => null,
                'userId' => '6f51622eb3814c75ae0263cece27ce72',
                'createdAt' => '2012-08-31T00:00:00+00:00',
                'updatedAt' => '2017-11-21T11:25:34+00:00',
                'title' => '2',
                'alt' => null,
                'url' => '',
                'hasFile' => false,
                'fileName' => null,
                'mediaType' => null,
                'uploadedAt' => null,
                'mediaFolderId' => null,
                'attributes' => null,
                'translated' => [],
            ],
            'links' => [
                'self' => '/api/media/548faa1f7846436c85944f4aea792d96',
            ],
            'relationships' => [
                'translations' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/translations',
                    ],
                ],
                'user' => [
                    'data' => [
                        'id' => '6f51622eb3814c75ae0263cece27ce72',
                        'type' => 'user',
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
                'propertyGroupOptions' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/property-group-options',
                    ],
                ],
                'tags' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/tags',
                    ],
                ],
                'avatarUser' => [
                    'data' => null,
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/avatar-user',
                    ],
                ],
                'mailTemplateMedia' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/mail-template-media',
                    ],
                ],
                'shippingMethods' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/shipping-methods',
                    ],
                ],
                'paymentMethods' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/payment-methods',
                    ],
                ],
                'productConfiguratorSettings' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/product-configurator-settings',
                    ],
                ],
                'orderLineItems' => [
                    'data' => [],
                    'links' => [
                        'related' => '/api/media/548faa1f7846436c85944f4aea792d96/order-line-items',
                    ],
                ],
            ],
            'meta' => null,
        ],
    ],
];
