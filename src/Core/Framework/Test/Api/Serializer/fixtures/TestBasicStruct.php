<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;

/**
 * @internal
 */
class TestBasicStruct extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $media = new MediaEntity();
        $media->setId('1d23c1b015bf43fb97e89008cf42d6fe');
        $media->setTitle('Manufacturer');
        $media->setMimeType('image/png');
        $media->setFileExtension('png');
        $media->setFileSize(310818);
        $media->setAlt('A media object description');
        $media->setCreatedAt(new \DateTime('2018-01-15T08:01:16.432+00:00'));
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
                'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                'type' => 'media',
                'attributes' => [
                    'userId' => null,
                    'mediaFolderId' => null,
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'uploadedAt' => null,
                    'fileName' => null,
                    'fileSize' => 310818,
                    'metaData' => null,
                    'mediaType' => null,
                    'createdAt' => '2018-01-15T08:01:16.432+00:00',
                    'updatedAt' => null,
                    'alt' => 'A media object description',
                    'title' => 'Manufacturer',
                    'url' => '',
                    'customFields' => null,
                    'hasFile' => false,
                    'translated' => [],
                    'private' => false,
                ],
                'links' => [
                    'self' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe', $baseUrl),
                ],
                'relationships' => [
                    'user' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/user', $baseUrl),
                        ],
                    ],
                    'categories' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/categories', $baseUrl),
                        ],
                    ],
                    'productManufacturers' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/product-manufacturers', $baseUrl),
                        ],
                    ],
                    'productMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/product-media', $baseUrl),
                        ],
                    ],
                    'avatarUsers' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/avatar-users', $baseUrl),
                        ],
                    ],
                    'translations' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/translations', $baseUrl),
                        ],
                    ],
                    'thumbnails' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/thumbnails', $baseUrl),
                        ],
                    ],
                    'mediaFolder' => [
                        'data' => null,
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/media-folder', $baseUrl),
                        ],
                    ],
                    'propertyGroupOptions' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/property-group-options', $baseUrl),
                        ],
                    ],
                    'tags' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/tags', $baseUrl),
                        ],
                    ],
                    'mailTemplateMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/mail-template-media', $baseUrl),
                        ],
                    ],
                    'documentBaseConfigs' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/document-base-configs', $baseUrl),
                        ],
                    ],
                    'shippingMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/shipping-methods', $baseUrl),
                        ],
                    ],
                    'paymentMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/payment-methods', $baseUrl),
                        ],
                    ],
                    'productConfiguratorSettings' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/product-configurator-settings', $baseUrl),
                        ],
                    ],
                    'orderLineItems' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/order-line-items', $baseUrl),
                        ],
                    ],
                    'cmsBlocks' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/cms-blocks', $baseUrl),
                        ],
                    ],
                    'cmsSections' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/cms-sections', $baseUrl),
                        ],
                    ],
                    'cmsPages' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/cms-pages', $baseUrl),
                        ],
                    ],
                    'documents' => [
                        'data' => [],
                        'links' => [
                            'related' => sprintf('%s/media/1d23c1b015bf43fb97e89008cf42d6fe/documents', $baseUrl),
                        ],
                    ],
                ],
                'meta' => null,
            ],
            'included' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'userId' => null,
            'mediaFolderId' => null,
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'uploadedAt' => null,
            'fileName' => null,
            'fileSize' => 310818,
            'metaData' => null,
            'mediaType' => null,
            'createdAt' => '2018-01-15T08:01:16.432+00:00',
            'updatedAt' => null,
            'alt' => 'A media object description',
            'title' => 'Manufacturer',
            'url' => '',
            'customFields' => null,
            'hasFile' => false,
            'translated' => [],
            'private' => false,
            'user' => null,
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
            '_uniqueIdentifier' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'versionId' => null,
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
            $fixtures['data']['relationships']['tags'],
            $fixtures['data']['relationships']['categories'],
            $fixtures['data']['relationships']['productManufacturers'],
            $fixtures['data']['relationships']['productMedia'],
            $fixtures['data']['relationships']['mediaFolder'],
            $fixtures['data']['relationships']['propertyGroupOptions'],
            $fixtures['data']['relationships']['mailTemplateMedia'],
            $fixtures['data']['relationships']['documentBaseConfigs'],
            $fixtures['data']['relationships']['shippingMethods'],
            $fixtures['data']['relationships']['paymentMethods'],
            $fixtures['data']['relationships']['productConfiguratorSettings'],
            $fixtures['data']['relationships']['orderLineItems'],
            $fixtures['data']['relationships']['cmsBlocks'],
            $fixtures['data']['relationships']['cmsSections'],
            $fixtures['data']['relationships']['cmsPages'],
            $fixtures['data']['relationships']['documents'],
            $fixtures['data']['relationships']['mediaFolderId']
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
            $fixtures['tags'],
            $fixtures['categories'],
            $fixtures['productManufacturers'],
            $fixtures['productMedia'],
            $fixtures['mediaFolder'],
            $fixtures['propertyGroupOptions'],
            $fixtures['mailTemplateMedia'],
            $fixtures['documentBaseConfigs'],
            $fixtures['shippingMethods'],
            $fixtures['paymentMethods'],
            $fixtures['productConfiguratorSettings'],
            $fixtures['orderLineItems'],
            $fixtures['cmsBlocks'],
            $fixtures['cmsSections'],
            $fixtures['cmsPages'],
            $fixtures['documents'],
            $fixtures['mediaFolderId']
        );

        return $fixtures;
    }
}
