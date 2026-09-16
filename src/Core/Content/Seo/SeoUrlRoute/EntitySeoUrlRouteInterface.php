<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
interface EntitySeoUrlRouteInterface
{
    public function getConfig(): SeoUrlRouteConfig;

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void;
}
