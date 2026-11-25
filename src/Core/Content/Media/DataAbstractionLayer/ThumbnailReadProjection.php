<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Projection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ThumbnailReadProjection implements Projection
{
    public function __construct(private readonly bool $remoteThumbnailsEnabled = false)
    {
    }

    public function project(?Criteria $criteria, EntityDefinition $definition, Field $field, string $root, QueryBuilder $query): void
    {
        if ($this->remoteThumbnailsEnabled || $criteria?->hasAssociation('thumbnails')) {
            return;
        }

        $parent = EntityDefinitionQueryHelper::escape($root);
        $alias = EntityDefinitionQueryHelper::escape($root . '.' . $field->getPropertyName());

        $query->addSelect(<<<SQL
            COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT(
                'id', LOWER(HEX(t.id)), 'mediaId', LOWER(HEX(t.media_id)),
                'mediaThumbnailSizeId', LOWER(HEX(t.media_thumbnail_size_id)),
                'width', t.width, 'height', t.height, 'path', t.path,
                'createdAt', t.created_at, 'updatedAt', t.updated_at,
                'customFields', CAST(t.custom_fields AS CHAR CHARACTER SET utf8mb4)
            )) FROM media_thumbnail t WHERE t.media_id = {$parent}.id), JSON_ARRAY()) AS {$alias}
            SQL);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function hydrate(array $rows, EntityDefinition $definition): MediaThumbnailCollection
    {
        $collection = new MediaThumbnailCollection();
        foreach ($rows as $row) {
            $row['createdAt'] = new \DateTimeImmutable($row['createdAt']);
            if ($row['updatedAt'] !== null) {
                $row['updatedAt'] = new \DateTimeImmutable($row['updatedAt']);
            }
            if (isset($row['customFields'])) {
                $association = $definition->getField('thumbnails');
                \assert($association instanceof OneToManyAssociationField);
                $row['customFields'] = $association->getReferenceDefinition()->decode('customFields', $row['customFields']);
            }

            $collection->add((new MediaThumbnailEntity())->assign($row));
        }

        return $collection;
    }
}
