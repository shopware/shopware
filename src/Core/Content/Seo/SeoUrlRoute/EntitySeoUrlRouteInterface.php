<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface EntitySeoUrlRouteInterface
{
    public function getConfig(): SeoUrlRouteConfig;
}
