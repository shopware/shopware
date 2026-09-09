<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface SeoUrlRouteLoaderInterface
{
    /**
     * @return iterable<SeoUrlRouteInterface>
     */
    public function load(): iterable;
}
