<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface UrlProviderInterface
{
    public function getName(): string;

    public function getUrls(SalesChannelContext $salesChannelContext, int $limit, ?int $offset = null): UrlResult;
}
