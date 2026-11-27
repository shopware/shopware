<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Cache;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
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
#[Package('framework')]
class EntityCacheTagResolver
{
    /**
     * @return list<string>|null
     */
    public function resolve(EntityDefinition $definition, string $primaryKey): ?array
    {
        return match ($definition->getEntityName()) {
            ProductDefinition::ENTITY_NAME => [EntityCacheKeyGenerator::buildProductTag($primaryKey)],
            CategoryDefinition::ENTITY_NAME => [CategoryRoute::buildName($primaryKey)],
            LandingPageDefinition::ENTITY_NAME => [LandingPageRoute::buildName($primaryKey)],
            CmsPageDefinition::ENTITY_NAME => [EntityCacheKeyGenerator::buildCmsTag($primaryKey)],
            ProductStreamDefinition::ENTITY_NAME => [EntityCacheKeyGenerator::buildStreamTag($primaryKey)],
            MediaDefinition::ENTITY_NAME => [],
            default => null,
        };
    }
}
