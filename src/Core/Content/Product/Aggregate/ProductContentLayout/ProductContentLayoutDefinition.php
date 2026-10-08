<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_product_content_layout';

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

    public function getContentLayoutDefaultConfigKey(): string
    {
        return self::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT;
    }

    public function getPageDataRequirements(): array
    {
        return [
            ...parent::getPageDataRequirements(),
            new DataRequirement(
                'configuratorSettings',
                ProductConfiguratorDataLoader::SOURCE,
                new ProductConfiguratorLoaderConfig(productId: 'productId'),
            ),
            new DataRequirement(
                'reviews',
                ProductReviewDataLoader::SOURCE,
                new ProductReviewLoaderConfig(property: 'productId')
            ),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return [
            'manufacturer.media',
            'options.group',
            'properties.group',
            'mainCategories.category',
            'media.media',
            // The picture behind the cover assignment, which `product.cover` needs to project to a
            // MediaEntity. SalesChannelProductDefinition::processCriteria() happens to add this one too, but
            // it adds it for the storefront's own reasons and only while the criteria selects no fields —
            // too conditional for a mapping candidate to rest on, so the requirement is declared here.
            'cover.media',
        ];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('product_id', 'productId');
    }
}
