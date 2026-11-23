<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRoute implements EntitySeoUrlRouteInterface
{
    public const TARGET_ROUTE = 'frontend.script_endpoint';

    private const ROUTE_NAME_PREFIX = 'storefront.app.';

    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly AppEntitySeoUrlConfig $seoUrl,
    ) {
    }

    public static function buildRouteName(string $appName, string $name): string
    {
        return self::routeNamePrefix($appName) . $name;
    }

    public static function routeNamePrefix(string $appName): string
    {
        return self::ROUTE_NAME_PREFIX . $appName . '.';
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->definition,
            $this->seoUrl->getRouteName(),
            $this->seoUrl->getDefaultTemplate(),
            true,
            'id',
            self::TARGET_ROUTE,
            ['hook' => $this->seoUrl->getHook()]
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
    }
}
