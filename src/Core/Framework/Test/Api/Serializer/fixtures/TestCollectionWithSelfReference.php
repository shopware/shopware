<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;

/**
 * @internal
 */
class TestCollectionWithSelfReference extends SerializationFixture
{
    /**
     * @return MediaFolderCollection|MediaFolderEntity
     */
    public function getInput(): EntityCollection|Entity
    {
        $parent = new MediaFolderEntity();
        $parent->setId('3e352be2d85846dd97529c0f6b544870');
        $parent->setChildCount(1);
        $parent->setUseParentConfiguration(false);
        $parent->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $parent->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $parent->internalSetEntityData('media_folder', new FieldVisibility([]));

        $child = new MediaFolderEntity();
        $child->setId('5846dd97529c0f6b5448713e352be2d8');
        $child->setChildCount(1);
        $child->setUseParentConfiguration(true);
        $child->setParentId('3e352be2d85846dd97529c0f6b544870');
        $child->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $child->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $child->internalSetEntityData('media_folder', new FieldVisibility([]));
        $parent->setChildren(new MediaFolderCollection([$child]));

        return new MediaFolderCollection([$parent]);
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
                    'type' => 'media_folder',
                    'attributes' => [
                        'useParentConfiguration' => false,
                        'configurationId' => null,
                        'defaultFolderId' => null,
                        'parentId' => null,
                        'childCount' => 1,
                        'name' => null,
                        'createdAt' => '2012-08-15T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'customFields' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870', $baseUrl),
                    ],
                    'relationships' => [
                        'parent' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870/parent', $baseUrl),
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
                                'related' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870/children', $baseUrl),
                            ],
                        ],
                        'media' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870/media', $baseUrl),
                            ],
                        ],
                        'defaultFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870/default-folder', $baseUrl),
                            ],
                        ],
                        'configuration' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/3e352be2d85846dd97529c0f6b544870/configuration', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
            ],
            'included' => [
                [
                    'id' => '5846dd97529c0f6b5448713e352be2d8',
                    'type' => 'media_folder',
                    'attributes' => [
                        'useParentConfiguration' => true,
                        'configurationId' => null,
                        'defaultFolderId' => null,
                        'parentId' => '3e352be2d85846dd97529c0f6b544870',
                        'childCount' => 1,
                        'name' => null,
                        'createdAt' => '2012-08-15T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'customFields' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8', $baseUrl),
                    ],
                    'relationships' => [
                        'parent' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8/parent', $baseUrl),
                            ],
                        ],
                        'children' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8/children', $baseUrl),
                            ],
                        ],
                        'media' => [
                            'data' => [],
                            'links' => [
                                'related' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8/media', $baseUrl),
                            ],
                        ],
                        'defaultFolder' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8/default-folder', $baseUrl),
                            ],
                        ],
                        'configuration' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/media-folder/5846dd97529c0f6b5448713e352be2d8/configuration', $baseUrl),
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
                'name' => null,
                'parentId' => null,
                'parent' => null,
                'childCount' => 1,
                'media' => null,
                'configurationId' => null,
                'configuration' => null,
                'useParentConfiguration' => false,
                'children' => [
                    [
                        'id' => '5846dd97529c0f6b5448713e352be2d8',
                        'name' => null,
                        'parentId' => '3e352be2d85846dd97529c0f6b544870',
                        'parent' => null,
                        'childCount' => 1,
                        'media' => null,
                        'configurationId' => null,
                        'configuration' => null,
                        'useParentConfiguration' => true,
                        'children' => null,
                        'defaultFolder' => null,
                        'defaultFolderId' => null,
                        'customFields' => null,
                        '_uniqueIdentifier' => '5846dd97529c0f6b5448713e352be2d8',
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => '2012-08-15T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'extensions' => [],
                        'apiAlias' => 'media_folder',
                    ],
                ],
                'defaultFolder' => null,
                'defaultFolderId' => null,
                'customFields' => null,
                '_uniqueIdentifier' => '3e352be2d85846dd97529c0f6b544870',
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2012-08-15T00:00:00.000+00:00',
                'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                'extensions' => [],
                'apiAlias' => 'media_folder',
            ],
        ];
    }
}
