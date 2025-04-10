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
class TestBasicWithToManyRelationships extends SerializationFixture
{
    private const USER_ID = '6f51622eb3814c75ae0263cece27ce72';
    private const LOCALE_ID = '0195146acfbd71038508c63b798b23b2';
    private const MEDIA_ID = '548faa1f7846436c85944f4aea792d96';

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

        $media = new MediaEntity();
        $media->setId(self::MEDIA_ID);
        $media->setUserId(self::USER_ID);
        $media->setMimeType('image/jpg');
        $media->setFileExtension('jpg');
        $media->setFileSize(93889);
        $media->setTitle('2');
        $media->setCreatedAt(new \DateTime('2012-08-31T00:00:00.000+00:00'));
        $media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media->setUser(clone $user);
        $media->internalSetEntityData('media', new FieldVisibility([]));

        $mediaCollection = new MediaCollection([$media]);
        $user->setMedia($mediaCollection);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
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
                        'data' => [
                            [
                                'type' => 'media',
                                'id' => self::MEDIA_ID,
                            ],
                        ],
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
            'included' => [
                [
                    'id' => self::MEDIA_ID,
                    'type' => 'media',
                    'attributes' => [
                        'userId' => self::USER_ID,
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
                        'self' => \sprintf('%s/media/%s', $baseUrl, self::MEDIA_ID),
                    ],
                    'relationships' => [
                        'user' => [
                            'data' => [
                                'type' => 'user',
                                'id' => self::USER_ID,
                            ],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/user', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'categories' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/categories', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'productManufacturers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-manufacturers', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'productMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-media', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'avatarUsers' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/avatar-users', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'translations' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/translations', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'thumbnails' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/thumbnails', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'mediaFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/media/%s/media-folder', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'propertyGroupOptions' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/property-group-options', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'tags' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/tags', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'mailTemplateMedia' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/mail-template-media', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'documentBaseConfigs' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/document-base-configs', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/shipping-methods', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/payment-methods', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'productConfiguratorSettings' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/product-configurator-settings', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'orderLineItems' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/order-line-items', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'cmsBlocks' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-blocks', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'cmsSections' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-sections', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'cmsPages' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/cms-pages', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                        'documents' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/media/%s/documents', $baseUrl, self::MEDIA_ID),
                            ],
                        ],
                    ],
                    'meta' => [],
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
            'id' => self::USER_ID,
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
            'media' => [
                [
                    'id' => self::MEDIA_ID,
                    'userId' => self::USER_ID,
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
                    'mediaFolder' => null,
                    'propertyGroupOptions' => null,
                    'mailTemplateMedia' => null,
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
                    '_uniqueIdentifier' => self::MEDIA_ID,
                    'versionId' => null,
                    'extensions' => [],
                    'apiAlias' => 'media',
                ],
            ],
            'locale' => null,
            'avatarMedia' => null,
            'accessKeys' => null,
            'stateMachineHistoryEntries' => null,
            'importExportLogEntries' => null,
            'recoveryUser' => null,
            '_uniqueIdentifier' => self::USER_ID,
            'versionId' => null,
            'translated' => [],
            'extensions' => [],
            'admin' => true,
            'title' => null,
            'aclRoles' => null,
            'apiAlias' => 'user',
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
            $fixtures['data']['relationships']['recoveryUser'],
            $fixtures['data']['relationships']['aclRoles'],
            $fixtures['included'][0]['attributes']['userId'],
            $fixtures['included'][0]['attributes']['mediaType'],
            $fixtures['included'][0]['attributes']['mediaFolderId'],

            $fixtures['included'][0]['relationships']['user'],
            $fixtures['included'][0]['relationships']['avatarUsers'],
            $fixtures['included'][0]['relationships']['categories'],
            $fixtures['included'][0]['relationships']['productManufacturers'],
            $fixtures['included'][0]['relationships']['productMedia'],
            $fixtures['included'][0]['relationships']['mediaFolder'],
            $fixtures['included'][0]['relationships']['propertyGroupOptions'],
            $fixtures['included'][0]['relationships']['mailTemplateMedia'],
            $fixtures['included'][0]['relationships']['documentBaseConfigs'],
            $fixtures['included'][0]['relationships']['shippingMethods'],
            $fixtures['included'][0]['relationships']['paymentMethods'],
            $fixtures['included'][0]['relationships']['productConfiguratorSettings'],
            $fixtures['included'][0]['relationships']['orderLineItems'],
            $fixtures['included'][0]['relationships']['cmsBlocks'],
            $fixtures['included'][0]['relationships']['cmsSections'],
            $fixtures['included'][0]['relationships']['cmsPages'],
            $fixtures['included'][0]['relationships']['documents']
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
            $fixtures['recoveryUser'],
            $fixtures['aclRoles'],
            $fixtures['media'][0]['userId'],
            $fixtures['media'][0]['user'],
            $fixtures['media'][0]['avatarUsers'],
            $fixtures['media'][0]['mediaType'],
            $fixtures['media'][0]['categories'],
            $fixtures['media'][0]['productManufacturers'],
            $fixtures['media'][0]['productMedia'],
            $fixtures['media'][0]['mediaFolderId'],
            $fixtures['media'][0]['mediaFolder'],
            $fixtures['media'][0]['propertyGroupOptions'],
            $fixtures['media'][0]['mailTemplateMedia'],
            $fixtures['media'][0]['documentBaseConfigs'],
            $fixtures['media'][0]['shippingMethods'],
            $fixtures['media'][0]['paymentMethods'],
            $fixtures['media'][0]['productConfiguratorSettings'],
            $fixtures['media'][0]['orderLineItems'],
            $fixtures['media'][0]['cmsBlocks'],
            $fixtures['media'][0]['cmsSections'],
            $fixtures['media'][0]['cmsPages'],
            $fixtures['media'][0]['documents']
        );

        return $fixtures;
    }
}
