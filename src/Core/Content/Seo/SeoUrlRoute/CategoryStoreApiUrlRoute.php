<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryStoreApiUrlRoute implements EntitySeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'store-api.category.detail';

    /**
     * @internal
     */
    public function __construct(private readonly CategoryDefinition $categoryDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            '',
            true,
            'navigationId'
        );
    }
}
