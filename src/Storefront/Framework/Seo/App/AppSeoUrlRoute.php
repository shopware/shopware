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

    public const PATH_PREFIX = '/storefront/script/';

    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly string $routeName,
        private readonly string $hook,
        private readonly string $defaultTemplate,
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->definition,
            $this->routeName,
            $this->defaultTemplate,
            true,
            'id',
            self::TARGET_ROUTE,
            ['hook' => $this->hook]
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
    }
}
