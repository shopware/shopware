<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class TestBasicWithToOneRelationship extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $userId = '6f51622eb3814c75ae0263cece27ce72';

        $user = new UserEntity();
        $user->setId($userId);
        $user->setFirstName('Manufacturer');
        $user->setLastName('');
        $user->setPassword('password');
        $user->setUsername('user1');
        $user->setActive(true);
        $user->setAdmin(true);
        $user->setEmail('user1@shop.de');
        $user->setCreatedAt(new \DateTime('2018-01-15T08:01:16.000+00:00'));
        $user->internalSetEntityData('user', new FieldVisibility([]));

        $media = new MediaEntity();
        $media->setId('548faa1f7846436c85944f4aea792d96');
        $media->setUserId($userId);
        $media->setMimeType('image/jpg');
        $media->setFileExtension('jpg');
        $media->setFileSize(93889);
        $media->setTitle('2');
        $media->setCreatedAt(new \DateTime('2012-08-31T00:00:00.000+00:00'));
        $media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media->setUser($user);
        $media->internalSetEntityData('media', new FieldVisibility([]));

        return $media;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
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
                    'createdAt' => '2012-08-31T00:00:00.000+00:00',
                    'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                    'alt' => null,
                    'title' => '2',
                    'url' => '',
                    'customFields' => null,
                    'hasFile' => false,
                    'translated' => [],
                    'private' => false,
                ],
                'links' => [
                    'self' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96', $baseUrl),
                ],
                'relationships' => [
                    'user' => [
                        'data' => [
                            'type' => 'user',
                            'id' => '6f51622eb3814c75ae0263cece27ce72',
                        ],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/user', $baseUrl),
                        ],
                    ],
                    'categories' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/categories', $baseUrl),
                        ],
                    ],
                    'productManufacturers' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/product-manufacturers', $baseUrl),
                        ],
                    ],
                    'productMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/product-media', $baseUrl),
                        ],
                    ],
                    'avatarUsers' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/avatar-users', $baseUrl),
                        ],
                    ],
                    'translations' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/translations', $baseUrl),
                        ],
                    ],
                    'thumbnails' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/thumbnails', $baseUrl),
                        ],
                    ],
                    'mediaFolder' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/media-folder', $baseUrl),
                        ],
                    ],
                    'propertyGroupOptions' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/property-group-options', $baseUrl),
                        ],
                    ],
                    'tags' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/tags', $baseUrl),
                        ],
                    ],
                    'mailTemplateMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/mail-template-media', $baseUrl),
                        ],
                    ],
                    'documentBaseConfigs' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/document-base-configs', $baseUrl),
                        ],
                    ],
                    'shippingMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/shipping-methods', $baseUrl),
                        ],
                    ],
                    'paymentMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/payment-methods', $baseUrl),
                        ],
                    ],
                    'productConfiguratorSettings' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/product-configurator-settings', $baseUrl),
                        ],
                    ],
                    'orderLineItems' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/order-line-items', $baseUrl),
                        ],
                    ],
                    'cmsBlocks' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/cms-blocks', $baseUrl),
                        ],
                    ],
                    'cmsSections' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/cms-sections', $baseUrl),
                        ],
                    ],
                    'cmsPages' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/cms-pages', $baseUrl),
                        ],
                    ],
                    'documents' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/548faa1f7846436c85944f4aea792d96/documents', $baseUrl),
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
                        'firstName' => 'Manufacturer',
                        'lastName' => '',
                        'email' => 'user1@shop.de',
                        'active' => true,
                        'customFields' => null,
                        'createdAt' => '2018-01-15T08:01:16.000+00:00',
                        'updatedAt' => null,
                        'admin' => true,
                        'title' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72', $baseUrl),
                    ],
                    'relationships' => [
                        'locale' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/locale', $baseUrl),
                            ],
                        ],
                        'avatarMedia' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/avatar-media', $baseUrl),
                            ],
                        ],
                        'media' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/media', $baseUrl),
                            ],
                        ],
                        'accessKeys' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/access-keys', $baseUrl),
                            ],
                        ],
                        'stateMachineHistoryEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/state-machine-history-entries', $baseUrl),
                            ],
                        ],
                        'importExportLogEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/import-export-log-entries', $baseUrl),
                            ],
                        ],
                        'recoveryUser' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/recovery-user', $baseUrl),
                            ],
                        ],
                        'aclRoles' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/user/6f51622eb3814c75ae0263cece27ce72/acl-roles', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'id' => '548faa1f7846436c85944f4aea792d96',
            'userId' => '6f51622eb3814c75ae0263cece27ce72',
            'mimeType' => 'image/jpg',
            'fileExtension' => 'jpg',
            'fileSize' => 93889,
            'title' => '2',
            'metaData' => null,
            'mediaType' => null,
            'uploadedAt' => null,
            'alt' => null,
            'url' => '',
            'fileName' => null,
            'user' => [
                'id' => '6f51622eb3814c75ae0263cece27ce72',
                'localeId' => null,
                'avatarId' => null,
                'username' => 'user1',
                'firstName' => 'Manufacturer',
                'lastName' => '',
                'email' => 'user1@shop.de',
                'active' => true,
                'locale' => null,
                'avatarMedia' => null,
                'media' => null,
                'accessKeys' => null,
                'stateMachineHistoryEntries' => null,
                'importExportLogEntries' => null,
                'recoveryUser' => null,
                'customFields' => null,
                '_uniqueIdentifier' => '6f51622eb3814c75ae0263cece27ce72',
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2018-01-15T08:01:16.000+00:00',
                'updatedAt' => null,
                'extensions' => [],
                'admin' => true,
                'title' => null,
                'aclRoles' => null,
                'apiAlias' => 'user',
            ],
            'translations' => null,
            'categories' => null,
            'productManufacturers' => null,
            'productMedia' => null,
            'avatarUsers' => null,
            'thumbnails' => null,
            'mediaFolderId' => null,
            'mediaFolder' => null,
            'hasFile' => false,
            'private' => false,
            'propertyGroupOptions' => null,
            'mailTemplateMedia' => null,
            'customFields' => null,
            'tags' => null,
            'documentBaseConfigs' => null,
            'shippingMethods' => null,
            'paymentMethods' => null,
            'productConfiguratorSettings' => null,
            'orderLineItems' => null,
            'cmsBlocks' => null,
            'cmsSections' => null,
            'cmsPages' => null,
            'documents' => null,
            '_uniqueIdentifier' => '548faa1f7846436c85944f4aea792d96',
            'versionId' => null,
            'translated' => [],
            'createdAt' => '2012-08-31T00:00:00.000+00:00',
            'updatedAt' => '2017-11-21T11:25:34.000+00:00',
            'extensions' => [],
            'apiAlias' => 'media',
        ];
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedSalesChannelJsonApiData(array $fixtures): array
    {
        unset(
            $fixtures['data']['attributes']['userId'],
            $fixtures['data']['attributes']['mediaType'],
            $fixtures['data']['attributes']['mediaFolderId'],

            $fixtures['data']['relationships']['user'],
            $fixtures['data']['relationships']['avatarUsers'],
            $fixtures['data']['relationships']['categories'],
            $fixtures['data']['relationships']['productManufacturers'],
            $fixtures['data']['relationships']['productMedia'],
            $fixtures['data']['relationships']['mediaFolder'],
            $fixtures['data']['relationships']['propertyGroupOptions'],
            $fixtures['data']['relationships']['mailTemplateMedia'],
            $fixtures['data']['relationships']['documentBaseConfigs'],
            $fixtures['data']['relationships']['shippingMethods'],
            $fixtures['data']['relationships']['paymentMethods'],
            $fixtures['data']['relationships']['tags'],
            $fixtures['data']['relationships']['productConfiguratorSettings'],
            $fixtures['data']['relationships']['orderLineItems'],
            $fixtures['data']['relationships']['cmsBlocks'],
            $fixtures['data']['relationships']['cmsSections'],
            $fixtures['data']['relationships']['cmsPages'],
            $fixtures['data']['relationships']['documents'],

            $fixtures['included'][0]
        );

        return $fixtures;
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedSalesChannelJsonData(array $fixtures): array
    {
        unset(
            $fixtures['userId'],
            $fixtures['user'],
            $fixtures['avatarUsers'],
            $fixtures['mediaType'],
            $fixtures['categories'],
            $fixtures['productManufacturers'],
            $fixtures['productMedia'],
            $fixtures['mediaFolderId'],
            $fixtures['mediaFolder'],
            $fixtures['propertyGroupOptions'],
            $fixtures['mailTemplateMedia'],
            $fixtures['tags'],
            $fixtures['documentBaseConfigs'],
            $fixtures['shippingMethods'],
            $fixtures['paymentMethods'],
            $fixtures['productConfiguratorSettings'],
            $fixtures['orderLineItems'],
            $fixtures['cmsBlocks'],
            $fixtures['cmsSections'],
            $fixtures['cmsPages'],
            $fixtures['documents']
        );

        return $fixtures;
    }
}
