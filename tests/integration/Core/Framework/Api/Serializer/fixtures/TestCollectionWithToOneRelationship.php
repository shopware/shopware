<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class TestCollectionWithToOneRelationship extends SerializationFixture
{
    private const USER_ID = '6f51622eb3814c75ae0263cece27ce72';
    private const LOCALE_ID = '0195146acfbd71038508c63b798b23b2';
    private const MEDIA_ID_1 = '3e352be2d85846dd97529c0f6b544870';
    private const MEDIA_ID_2 = 'f1ad1d0c02454a40abf250f764d16248';

    /**
     * @return MediaCollection|MediaEntity
     */
    public function getInput(): EntityCollection|Entity
    {
        $user = new UserEntity();
        $user->setId(self::USER_ID);
        $user->setFirstName('Manufacturer');
        $user->setLastName('');
        $user->setPassword('password');
        $user->setUsername('user1');
        $user->setActive(true);
        $user->setAdmin(true);
        $user->setEmail('user1@shop.de');
        $user->setCreatedAt(new \DateTime('2018-01-15T08:01:16.000+00:00'));
        $user->internalSetEntityData('user', new FieldVisibility([]));
        $user->setLocaleId(self::LOCALE_ID);

        $media1 = new MediaEntity();
        $media1->setId(self::MEDIA_ID_1);
        $media1->setUser($user);
        $media1->setUserId(self::USER_ID);
        $media1->setMimeType('image/jpg');
        $media1->setFileExtension('jpg');
        $media1->setFileSize(18921);
        $media1->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $media1->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media1->setTitle('Lagerkorn-5,0klein');
        $media1->internalSetEntityData('media', new FieldVisibility([]));

        $media2 = new MediaEntity();
        $media2->setId(self::MEDIA_ID_2);
        $media2->setUser($user);
        $media2->setUserId(self::USER_ID);
        $media2->setMimeType('image/jpg');
        $media2->setFileExtension('jpg');
        $media2->setFileSize(155633);
        $media2->setCreatedAt(new \DateTime('2012-08-17T00:00:00.000+00:00'));
        $media2->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media2->setTitle('Jasmine-Lotus-Cover');
        $media2->internalSetEntityData('media', new FieldVisibility([]));

        return new MediaCollection([$media1, $media2]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                [
                    'id' => self::MEDIA_ID_1,
                    'type' => 'media',
                    'attributes' => [
                        'userId' => self::USER_ID,
                        'mediaFolderId' => null,
                        'mimeType' => 'image/jpg',
                        'fileExtension' => 'jpg',
                        'uploadedAt' => null,
                        'fileName' => null,
                        'fileSize' => 18921,
                        'metaData' => null,
                        'mediaType' => null,
                        'createdAt' => '2012-08-15T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'alt' => null,
                        'title' => 'Lagerkorn-5,0klein',
                        'url' => '',
                        'customFields' => null,
                        'hasFile' => false,
                        'translated' => [],
                        'private' => false,
                    ],
                    'links' => [
                        'self' => \sprintf('%s/media/%s', $baseUrl, self::MEDIA_ID_1),
                    ],
                    'relationships' => [
                        'user' => [
                            'data' => [
                                'type' => 'user',
                                'id' => self::USER_ID,
                            ],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/user', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'categories' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/categories', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'productManufacturers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-manufacturers', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'productMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-media', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'avatarUsers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/avatar-users', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'translations' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/translations', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'thumbnails' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/thumbnails', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'mediaFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/media/%s/media-folder', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'propertyGroupOptions' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/property-group-options', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'tags' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/tags', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'mailTemplateMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/mail-template-media', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'documentBaseConfigs' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/document-base-configs', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/shipping-methods', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/payment-methods', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'productConfiguratorSettings' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-configurator-settings', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'orderLineItems' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/order-line-items', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'cmsBlocks' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-blocks', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'cmsSections' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-sections', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'cmsPages' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-pages', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                        'documents' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/documents', $baseUrl, self::MEDIA_ID_1),
                            ],
                        ],
                    ],
                    'meta' => [],
                ], [
                    'id' => self::MEDIA_ID_2,
                    'type' => 'media',
                    'attributes' => [
                        'userId' => self::USER_ID,
                        'mediaFolderId' => null,
                        'mimeType' => 'image/jpg',
                        'fileExtension' => 'jpg',
                        'uploadedAt' => null,
                        'fileName' => null,
                        'fileSize' => 155633,
                        'metaData' => null,
                        'mediaType' => null,
                        'createdAt' => '2012-08-17T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'alt' => null,
                        'title' => 'Jasmine-Lotus-Cover',
                        'url' => '',
                        'customFields' => null,
                        'hasFile' => false,
                        'translated' => [],
                        'private' => false,
                    ],
                    'links' => [
                        'self' => \sprintf('%s/media/%s', $baseUrl, self::MEDIA_ID_2),
                    ],
                    'relationships' => [
                        'user' => [
                            'data' => [
                                'type' => 'user',
                                'id' => self::USER_ID,
                            ],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/user', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'categories' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/categories', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'productManufacturers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-manufacturers', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'productMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-media', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'avatarUsers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/avatar-users', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'translations' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/translations', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'thumbnails' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/thumbnails', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'mediaFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/media/%s/media-folder', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'propertyGroupOptions' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/property-group-options', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'tags' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/tags', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'mailTemplateMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/mail-template-media', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'documentBaseConfigs' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/document-base-configs', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/shipping-methods', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/payment-methods', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'productConfiguratorSettings' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-configurator-settings', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'orderLineItems' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/order-line-items', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'cmsBlocks' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-blocks', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'cmsSections' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-sections', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'cmsPages' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-pages', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                        'documents' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/documents', $baseUrl, self::MEDIA_ID_2),
                            ],
                        ],
                    ],
                    'meta' => [],
                ],
            ],
            'included' => [
                [
                    'id' => self::USER_ID,
                    'type' => 'user',
                    'attributes' => [
                        'localeId' => self::LOCALE_ID,
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
                        'self' => \sprintf('%s/user/%s', $baseUrl, self::USER_ID),
                    ],
                    'relationships' => [
                        'locale' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/user/%s/locale', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'avatarMedia' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/user/%s/avatar-media', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'media' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/user/%s/media', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'accessKeys' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/user/%s/access-keys', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'stateMachineHistoryEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/user/%s/state-machine-history-entries', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'importExportLogEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/user/%s/import-export-log-entries', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'recoveryUser' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/user/%s/recovery-user', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'aclRoles' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/user/%s/acl-roles', $baseUrl, self::USER_ID),
                            ],
                        ],
                    ],
                    'meta' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getJsonFixtures(): array
    {
        return [
            [
                'id' => self::MEDIA_ID_1,
                'userId' => self::USER_ID,
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 18921,
                'title' => 'Lagerkorn-5,0klein',
                'metaData' => null,
                'mediaType' => null,
                'uploadedAt' => null,
                'alt' => null,
                'url' => '',
                'fileName' => null,
                'user' => [
                    'id' => self::USER_ID,
                    'localeId' => self::LOCALE_ID,
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
                    '_uniqueIdentifier' => self::USER_ID,
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
                '_uniqueIdentifier' => self::MEDIA_ID_1,
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2012-08-15T00:00:00.000+00:00',
                'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                'extensions' => [],
                'apiAlias' => 'media',
            ], [
                'id' => self::MEDIA_ID_2,
                'userId' => self::USER_ID,
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileSize' => 155633,
                'title' => 'Jasmine-Lotus-Cover',
                'metaData' => null,
                'mediaType' => null,
                'uploadedAt' => null,
                'alt' => null,
                'url' => '',
                'fileName' => null,
                'user' => [
                    'id' => self::USER_ID,
                    'localeId' => self::LOCALE_ID,
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
                    '_uniqueIdentifier' => self::USER_ID,
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
                '_uniqueIdentifier' => self::MEDIA_ID_2,
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2012-08-17T00:00:00.000+00:00',
                'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                'extensions' => [],
                'apiAlias' => 'media',
            ],
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
            $fixtures['data'][0]['attributes']['userId'],
            $fixtures['data'][0]['attributes']['mediaType'],
            $fixtures['data'][0]['attributes']['mediaFolderId'],
            $fixtures['data'][0]['relationships']['user'],
            $fixtures['data'][0]['relationships']['avatarUsers'],
            $fixtures['data'][0]['relationships']['categories'],
            $fixtures['data'][0]['relationships']['productManufacturers'],
            $fixtures['data'][0]['relationships']['productMedia'],
            $fixtures['data'][0]['relationships']['mediaFolder'],
            $fixtures['data'][0]['relationships']['propertyGroupOptions'],
            $fixtures['data'][0]['relationships']['mailTemplateMedia'],
            $fixtures['data'][0]['relationships']['documentBaseConfigs'],
            $fixtures['data'][0]['relationships']['shippingMethods'],
            $fixtures['data'][0]['relationships']['paymentMethods'],
            $fixtures['data'][0]['relationships']['productConfiguratorSettings'],
            $fixtures['data'][0]['relationships']['orderLineItems'],
            $fixtures['data'][0]['relationships']['cmsBlocks'],
            $fixtures['data'][0]['relationships']['cmsSections'],
            $fixtures['data'][0]['relationships']['cmsPages'],
            $fixtures['data'][0]['relationships']['documents'],
            $fixtures['data'][0]['relationships']['tags'],

            $fixtures['data'][1]['attributes']['userId'],
            $fixtures['data'][1]['attributes']['mediaType'],
            $fixtures['data'][1]['attributes']['mediaFolderId'],
            $fixtures['data'][1]['relationships']['user'],
            $fixtures['data'][1]['relationships']['avatarUsers'],
            $fixtures['data'][1]['relationships']['categories'],
            $fixtures['data'][1]['relationships']['productManufacturers'],
            $fixtures['data'][1]['relationships']['productMedia'],
            $fixtures['data'][1]['relationships']['mediaFolder'],
            $fixtures['data'][1]['relationships']['propertyGroupOptions'],
            $fixtures['data'][1]['relationships']['mailTemplateMedia'],
            $fixtures['data'][1]['relationships']['documentBaseConfigs'],
            $fixtures['data'][1]['relationships']['shippingMethods'],
            $fixtures['data'][1]['relationships']['paymentMethods'],
            $fixtures['data'][1]['relationships']['productConfiguratorSettings'],
            $fixtures['data'][1]['relationships']['orderLineItems'],
            $fixtures['data'][1]['relationships']['cmsBlocks'],
            $fixtures['data'][1]['relationships']['cmsSections'],
            $fixtures['data'][1]['relationships']['cmsPages'],
            $fixtures['data'][1]['relationships']['documents'],
            $fixtures['data'][1]['relationships']['tags'],

            $fixtures['included'][0]
        );

        return $fixtures;
    }

    /**
     * @param array<int, mixed> $fixtures
     *
     * @return array<int, mixed>
     */
    protected function removeProtectedSalesChannelJsonData(array $fixtures): array
    {
        unset(
            $fixtures[0]['userId'],
            $fixtures[0]['user'],
            $fixtures[0]['avatarUsers'],
            $fixtures[0]['mediaType'],
            $fixtures[0]['categories'],
            $fixtures[0]['productManufacturers'],
            $fixtures[0]['productMedia'],
            $fixtures[0]['mediaFolderId'],
            $fixtures[0]['mediaFolder'],
            $fixtures[0]['propertyGroupOptions'],
            $fixtures[0]['mailTemplateMedia'],
            $fixtures[0]['documentBaseConfigs'],
            $fixtures[0]['shippingMethods'],
            $fixtures[0]['paymentMethods'],
            $fixtures[0]['productConfiguratorSettings'],
            $fixtures[0]['orderLineItems'],
            $fixtures[0]['cmsBlocks'],
            $fixtures[0]['cmsSections'],
            $fixtures[0]['cmsPages'],
            $fixtures[0]['documents'],
            $fixtures[0]['tags'],

            $fixtures[1]['userId'],
            $fixtures[1]['user'],
            $fixtures[1]['avatarUsers'],
            $fixtures[1]['mediaType'],
            $fixtures[1]['categories'],
            $fixtures[1]['productManufacturers'],
            $fixtures[1]['productMedia'],
            $fixtures[1]['mediaFolderId'],
            $fixtures[1]['mediaFolder'],
            $fixtures[1]['propertyGroupOptions'],
            $fixtures[1]['mailTemplateMedia'],
            $fixtures[1]['documentBaseConfigs'],
            $fixtures[1]['shippingMethods'],
            $fixtures[1]['paymentMethods'],
            $fixtures[1]['productConfiguratorSettings'],
            $fixtures[1]['orderLineItems'],
            $fixtures[1]['cmsBlocks'],
            $fixtures[1]['cmsSections'],
            $fixtures[1]['cmsPages'],
            $fixtures[1]['tags'],
            $fixtures[1]['documents']
        );

        return $fixtures;
    }
}
