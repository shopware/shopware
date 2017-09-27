<?php

namespace Shopware\Album\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Extension\AlbumExtension;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AlbumBasicFactory extends Factory
{
    const ROOT_NAME = 'album';
    const EXTENSION_NAMESPACE = 'album';

    const FIELDS = [
       'uuid' => 'uuid',
       'parent_uuid' => 'parent_uuid',
       'position' => 'position',
       'create_thumbnails' => 'create_thumbnails',
       'thumbnail_size' => 'thumbnail_size',
       'icon' => 'icon',
       'thumbnail_high_dpi' => 'thumbnail_high_dpi',
       'thumbnail_quality' => 'thumbnail_quality',
       'thumbnail_high_dpi_quality' => 'thumbnail_high_dpi_quality',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        AlbumBasicStruct $album,
        QuerySelection $selection,
        TranslationContext $context
    ): AlbumBasicStruct {
        $album->setUuid((string) $data[$selection->getField('uuid')]);
        $album->setParentUuid(isset($data[$selection->getField('parent_uuid')]) ? (string) $data[$selection->getField('parent_uuid')] : null);
        $album->setPosition((int) $data[$selection->getField('position')]);
        $album->setCreateThumbnails((bool) $data[$selection->getField('create_thumbnails')]);
        $album->setThumbnailSize(isset($data[$selection->getField('thumbnail_size')]) ? (string) $data[$selection->getField('thumbnail_size')] : null);
        $album->setIcon(isset($data[$selection->getField('icon')]) ? (string) $data[$selection->getField('icon')] : null);
        $album->setThumbnailHighDpi((bool) $data[$selection->getField('thumbnail_high_dpi')]);
        $album->setThumbnailQuality(isset($data[$selection->getField('thumbnail_quality')]) ? (int) $data[$selection->getField('thumbnail_quality')] : null);
        $album->setThumbnailHighDpiQuality(isset($data[$selection->getField('thumbnail_high_dpi_quality')]) ? (int) $data[$selection->getField('thumbnail_high_dpi_quality')] : null);
        $album->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $album->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $album->setName((string) $data[$selection->getField('name')]);

        /** @var $extension AlbumExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($album, $data, $selection, $context);
        }

        return $album;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'album_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.album_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }
}
