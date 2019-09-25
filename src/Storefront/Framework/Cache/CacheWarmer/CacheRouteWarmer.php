<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class CacheRouteWarmer extends AbstractMessageHandler
{
    abstract public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage;

    protected function createHttpCacheKernel(HttpKernelInterface $kernel): HttpKernelInterface
    {
        if ($kernel instanceof HttpCache) {
            return $kernel;
        }

        if (!$kernel instanceof KernelInterface) {
            return $kernel;
        }

        $store = $kernel->getContainer()->get(CacheStore::class);

        return new HttpCache($kernel, $store, null);
    }
}
