<?php declare(strict_types=1);

namespace Shopware\Album\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Extension\AlbumExtension;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;

class AlbumBasicFactory extends Factory
{
    const ROOT_NAME = 'album';
    const EXTENSION_NAMESPACE = 'album';

    const FIELDS = [
       'uuid' => 'uuid',
       'parentUuid' => 'parent_uuid',
       'position' => 'position',
       'createThumbnails' => 'create_thumbnails',
       'thumbnailSize' => 'thumbnail_size',
       'icon' => 'icon',
       'thumbnailHighDpi' => 'thumbnail_high_dpi',
       'thumbnailQuality' => 'thumbnail_quality',
       'thumbnailHighDpiQuality' => 'thumbnail_high_dpi_quality',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
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
        $album->setParentUuid(isset($data[$selection->getField('parentUuid')]) ? (string) $data[$selection->getField('parentUuid')] : null);
        $album->setPosition((int) $data[$selection->getField('position')]);
        $album->setCreateThumbnails((bool) $data[$selection->getField('createThumbnails')]);
        $album->setThumbnailSize(isset($data[$selection->getField('thumbnailSize')]) ? (string) $data[$selection->getField('thumbnailSize')] : null);
        $album->setIcon(isset($data[$selection->getField('icon')]) ? (string) $data[$selection->getField('icon')] : null);
        $album->setThumbnailHighDpi((bool) $data[$selection->getField('thumbnailHighDpi')]);
        $album->setThumbnailQuality(isset($data[$selection->getField('thumbnailQuality')]) ? (int) $data[$selection->getField('thumbnailQuality')] : null);
        $album->setThumbnailHighDpiQuality(isset($data[$selection->getField('thumbnailHighDpiQuality')]) ? (int) $data[$selection->getField('thumbnailHighDpiQuality')] : null);
        $album->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $album->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
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
        $this->joinTranslation($selection, $query, $context);

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

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
