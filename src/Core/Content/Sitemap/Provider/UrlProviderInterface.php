<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.5.0 - This interface will be remove, use AbstractUrlProvider instead
 */
interface UrlProviderInterface
{
    public function getName(): string;

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult;
}
