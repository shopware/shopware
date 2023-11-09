<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use site crawlers for real cache warming
 */
#[Package('core')]
interface CacheRouteWarmer
{
    /**
     * @param array<mixed>|null $offset
     */
    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage;
}
