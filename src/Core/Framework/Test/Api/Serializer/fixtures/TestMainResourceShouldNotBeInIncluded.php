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
class TestMainResourceShouldNotBeInIncluded extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $mediaCollection = new MediaCollection();
        $userId = 'f343a3c119cf42a7841aa0ac5094908c';

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
        $media->setId('3e352be2d85846dd97529c0f6b544870');
        $media->setUser(clone $user);
        $media->setUserId($userId);
        $media->setMimeType('image/jpg');
        $media->setFileExtension('jpg');
        $media->setFileSize(18921);
        $media->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media->setTitle('Lagerkorn-5,0klein');
        $media->internalSetEntityData('media', new FieldVisibility([]));

        $mediaCollection->add($media);
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
                'id' => 'f343a3c119cf42a7841aa0ac5094908c',
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
                    'self' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c', $baseUrl),
                ],
                'relationships' => [
                    'locale' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/locale', $baseUrl),
                        ],
                    ],
                    'avatarMedia' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/avatar-media', $baseUrl),
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
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/media', $baseUrl),
                        ],
                    ],
                    'accessKeys' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/access-keys', $baseUrl),
                        ],
                    ],
                    'stateMachineHistoryEntries' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/state-machine-history-entries', $baseUrl),
                        ],
                    ],
                    'importExportLogEntries' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/import-export-log-entries', $baseUrl),
                        ],
                    ],
                    'recoveryUser' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/recovery-user', $baseUrl),
                        ],
                    ],
                    'aclRoles' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/user/f343a3c119cf42a7841aa0ac5094908c/acl-roles', $baseUrl),
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
                                'id' => 'f343a3c119cf42a7841aa0ac5094908c',
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
            'id' => 'f343a3c119cf42a7841aa0ac5094908c',
            'localeId' => null,
            'avatarId' => null,
            'username' => 'user1',
            'firstName' => 'Manufacturer',
            'lastName' => '',
            'email' => 'user1@shop.de',
            'active' => true,
            'locale' => null,
            'avatarMedia' => null,
            'apiAlias' => 'user',
            'media' => [
                [
                    'id' => '3e352be2d85846dd97529c0f6b544870',
                    'userId' => 'f343a3c119cf42a7841aa0ac5094908c',
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
                        'id' => 'f343a3c119cf42a7841aa0ac5094908c',
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
                        '_uniqueIdentifier' => 'f343a3c119cf42a7841aa0ac5094908c',
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
                ],
            ],
            'accessKeys' => null,
            'stateMachineHistoryEntries' => null,
            'importExportLogEntries' => null,
            'recoveryUser' => null,
            'customFields' => null,
            '_uniqueIdentifier' => 'f343a3c119cf42a7841aa0ac5094908c',
            'versionId' => null,
            'translated' => [],
            'createdAt' => '2018-01-15T08:01:16.000+00:00',
            'updatedAt' => null,
            'extensions' => [],
            'admin' => true,
            'title' => null,
            'aclRoles' => null,
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
            $fixtures['included'][0]['attributes']['userId'],
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
            $fixtures['media'][0]['mediaFolderId'],
            $fixtures['media'][0]['categories'],
            $fixtures['media'][0]['productManufacturers'],
            $fixtures['media'][0]['productMedia'],
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
