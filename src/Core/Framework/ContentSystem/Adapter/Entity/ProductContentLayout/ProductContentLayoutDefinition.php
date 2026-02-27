<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Entity\ProductContentLayout;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'product_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [EntityCacheKeyGenerator::buildProductTag($entityId)];
    }

    protected function getEntityAssociations(): array
    {
        return [
            'manufacturer.media',
            'options.group',
            'properties.group',
            'mainCategories.category',
            'media.media',
        ];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('product_id', 'productId');
    }
}
