<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer;
use Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('storefront')]
class ReverseProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('storefront.reverse_proxy.enabled')) {
            $container->removeDefinition('shopware.cache.reverse_proxy.redis');
            $container->removeDefinition(ReverseProxyCache::class);
            $container->removeDefinition(AbstractReverseProxyGateway::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);
            $container->removeDefinition(ReverseProxyCacheClearer::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);

            return;
        }

        $container->removeDefinition(CacheStore::class);

        $container->setAlias(CacheStore::class, ReverseProxyCache::class);
        $container->getAlias(CacheStore::class)->setPublic(true);

        if ($container->getParameter('storefront.reverse_proxy.fastly.enabled')) {
            $container->setAlias(AbstractReverseProxyGateway::class, FastlyReverseProxyGateway::class);
        } elseif ($container->getParameter('storefront.reverse_proxy.use_varnish_xkey')) {
            $container->setAlias(AbstractReverseProxyGateway::class, VarnishReverseProxyGateway::class);
        }
    }
}
