<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Cache;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves cache tags for entities based on the existing cache invalidation system.
 *
 * `resolve()` returns the tag of an entity, or null for an entity without a tag pattern.
 * `resolveRelated()` returns the parent and manufacturer tags that `ProductDetailRoute` records for a product, or [] for another entity.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityCacheTagResolver
{
    /**
     * @var array<string, string>
     */
    private const TAG_PATTERNS = [
        ProductDefinition::ENTITY_NAME => 'product-',
        CategoryDefinition::ENTITY_NAME => 'category-route-',
        LandingPageDefinition::ENTITY_NAME => 'landing-page-route-',
        CmsPageDefinition::ENTITY_NAME => 'cms-page-',
        ProductStreamDefinition::ENTITY_NAME => 'product-stream-',
    ];

    public function resolve(EntityDefinition $definition, string $primaryKey): ?string
    {
        $entityName = $definition->getEntityName();

        $prefix = self::TAG_PATTERNS[$entityName] ?? null;

        if ($prefix === null) {
            return null;
        }

        return $prefix . $primaryKey;
    }

    /**
     * @return list<string>
     */
    public function resolveRelated(Entity $entity): array
    {
        if (!$entity instanceof ProductEntity) {
            return [];
        }

        $tags = [];

        $parentId = $entity->getParentId();
        if ($parentId !== null) {
            $tags[] = self::TAG_PATTERNS[ProductDefinition::ENTITY_NAME] . $parentId;
        }

        $manufacturerId = $entity->getManufacturerId();
        if ($manufacturerId !== null) {
            $tags[] = EntityCacheKeyGenerator::buildManufacturerTag($manufacturerId);
        }

        return $tags;
    }
}
