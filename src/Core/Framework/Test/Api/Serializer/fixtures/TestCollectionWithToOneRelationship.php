<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

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
    /**
     * @return MediaCollection|MediaEntity
     */
    public function getInput(): EntityCollection|Entity
    {
        $mediaCollection = new MediaCollection();
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

        $media1 = new MediaEntity();
        $media1->setId('3e352be2d85846dd97529c0f6b544870');
        $media1->setUser($user);
        $media1->setUserId($userId);
        $media1->setMimeType('image/jpg');
        $media1->setFileExtension('jpg');
        $media1->setFileSize(18921);
        $media1->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $media1->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media1->setTitle('Lagerkorn-5,0klein');
        $media1->internalSetEntityData('media', new FieldVisibility([]));

        $media2 = new MediaEntity();
        $media2->setId('f1ad1d0c02454a40abf250f764d16248');
        $media2->setUser($user);
        $media2->setUserId($userId);
        $media2->setMimeType('image/jpg');
        $media2->setFileExtension('jpg');
        $media2->setFileSize(155633);
        $media2->setCreatedAt(new \DateTime('2012-08-17T00:00:00.000+00:00'));
        $media2->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media2->setTitle('Jasmine-Lotus-Cover');
        $media2->internalSetEntityData('media', new FieldVisibility([]));

        $mediaCollection->add($media1);
        $mediaCollection->add($media2);

        return $mediaCollection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                [
                    'id' => '3e352be2d85846dd97529c0f6b544870',
                    'type' => 'media',
                    'attributes' => [
                        'userId' => '6f51622eb3814c75ae0263cece27ce72',
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
                        'self' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870', $baseUrl),
                    ],
                    'relationships' => [
                        'user' => [
                            'data' => [
                                'type' => 'user',
                                'id' => '6f51622eb3814c75ae0263cece27ce72',
                            ],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/user', $baseUrl),
                            ],
                        ],
                        'categories' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/categories', $baseUrl),
                            ],
                        ],
                        'productManufacturers' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/product-manufacturers', $baseUrl),
                            ],
                        ],
                        'productMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/product-media', $baseUrl),
                            ],
                        ],
                        'avatarUsers' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/avatar-users', $baseUrl),
                            ],
                        ],
                        'translations' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/translations', $baseUrl),
                            ],
                        ],
                        'thumbnails' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/thumbnails', $baseUrl),
                            ],
                        ],
                        'mediaFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/media-folder', $baseUrl),
                            ],
                        ],
                        'propertyGroupOptions' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/property-group-options', $baseUrl),
                            ],
                        ],
                        'tags' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/tags', $baseUrl),
                            ],
                        ],
                        'mailTemplateMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/mail-template-media', $baseUrl),
                            ],
                        ],
                        'documentBaseConfigs' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/document-base-configs', $baseUrl),
                            ],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/shipping-methods', $baseUrl),
                            ],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/payment-methods', $baseUrl),
                            ],
                        ],
                        'productConfiguratorSettings' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/product-configurator-settings', $baseUrl),
                            ],
                        ],
                        'orderLineItems' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/order-line-items', $baseUrl),
                            ],
                        ],
                        'cmsBlocks' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/cms-blocks', $baseUrl),
                            ],
                        ],
                        'cmsSections' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/cms-sections', $baseUrl),
                            ],
                        ],
                        'cmsPages' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/cms-pages', $baseUrl),
                            ],
                        ],
                        'documents' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/3e352be2d85846dd97529c0f6b544870/documents', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ], [
                    'id' => 'f1ad1d0c02454a40abf250f764d16248',
                    'type' => 'media',
                    'attributes' => [
                        'userId' => '6f51622eb3814c75ae0263cece27ce72',
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
                        'self' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248', $baseUrl),
                    ],
                    'relationships' => [
                        'user' => [
                            'data' => [
                                'type' => 'user',
                                'id' => '6f51622eb3814c75ae0263cece27ce72',
                            ],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/user', $baseUrl),
                            ],
                        ],
                        'categories' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/categories', $baseUrl),
                            ],
                        ],
                        'productManufacturers' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/product-manufacturers', $baseUrl),
                            ],
                        ],
                        'productMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/product-media', $baseUrl),
                            ],
                        ],
                        'avatarUsers' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/avatar-users', $baseUrl),
                            ],
                        ],
                        'translations' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/translations', $baseUrl),
                            ],
                        ],
                        'thumbnails' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/thumbnails', $baseUrl),
                            ],
                        ],
                        'mediaFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/media-folder', $baseUrl),
                            ],
                        ],
                        'propertyGroupOptions' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/property-group-options', $baseUrl),
                            ],
                        ],
                        'tags' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/tags', $baseUrl),
                            ],
                        ],
                        'mailTemplateMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/mail-template-media', $baseUrl),
                            ],
                        ],
                        'documentBaseConfigs' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/document-base-configs', $baseUrl),
                            ],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/shipping-methods', $baseUrl),
                            ],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/payment-methods', $baseUrl),
                            ],
                        ],
                        'productConfiguratorSettings' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/product-configurator-settings', $baseUrl),
                            ],
                        ],
                        'orderLineItems' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/order-line-items', $baseUrl),
                            ],
                        ],
                        'cmsBlocks' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/cms-blocks', $baseUrl),
                            ],
                        ],
                        'cmsSections' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/cms-sections', $baseUrl),
                            ],
                        ],
                        'cmsPages' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/cms-pages', $baseUrl),
                            ],
                        ],
                        'documents' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media/f1ad1d0c02454a40abf250f764d16248/documents', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
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
     * @return array<array<string, mixed>>
     */
    protected function getJsonFixtures(): array
    {
        return [
            [
                'id' => '3e352be2d85846dd97529c0f6b544870',
                'userId' => '6f51622eb3814c75ae0263cece27ce72',
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
                '_uniqueIdentifier' => '3e352be2d85846dd97529c0f6b544870',
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2012-08-15T00:00:00.000+00:00',
                'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                'extensions' => [],
                'apiAlias' => 'media',
            ], [
                'id' => 'f1ad1d0c02454a40abf250f764d16248',
                'userId' => '6f51622eb3814c75ae0263cece27ce72',
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
                '_uniqueIdentifier' => 'f1ad1d0c02454a40abf250f764d16248',
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
