<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ReverseProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('storefront.reverse_proxy.enabled')) {
            $container->removeDefinition('shopware.cache.reverse_proxy.redis');
            $container->removeDefinition(ReverseProxyCache::class);
            $container->removeDefinition(AbstractReverseProxyGateway::class);

            return;
        }

        $container->removeDefinition(CacheStore::class);
        $container->setAlias(CacheStore::class, ReverseProxyCache::class);
        $container->getAlias(CacheStore::class)->setPublic(true);
    }
}
