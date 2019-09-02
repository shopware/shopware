<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface UrlProviderInterface
{
    /**
     * @return Url[]
     */
    public function getUrls(SalesChannelContext $salesChannelContext): array;

    /**
     * Resets the provider for next sitemap generation
     */
    public function reset();
}
