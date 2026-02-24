<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Cache;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves cache tags for entities based on the existing cache invalidation system.
 *
 * Only entities with defined cache tag patterns are supported. For unsupported
 * entities, null is returned, indicating the page should not be cached.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
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
}
