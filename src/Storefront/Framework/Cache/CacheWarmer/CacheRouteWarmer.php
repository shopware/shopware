<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * @package storefront
 */
interface CacheRouteWarmer
{
    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage;
}
