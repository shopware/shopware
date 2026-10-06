<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
<<<<<<< HEAD
use Shopware\Core\Framework\Feature;
=======
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
>>>>>>> origin/trunk
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement. The reverse proxy store and gateway are selected at runtime by the `ReverseProxyServiceFactory`.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ReverseProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        if (!$container->getParameter('shopware.http_cache.reverse_proxy.enabled')) {
            $container->removeDefinition(ReverseProxyCache::class);
            $container->removeDefinition(AbstractReverseProxyGateway::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);

            return;
        }

        $container->removeDefinition(CacheStore::class);

        $container->setAlias(CacheStore::class, ReverseProxyCache::class);
        $container->getAlias(CacheStore::class)->setPublic(true);

        if ($container->getParameter('shopware.http_cache.reverse_proxy.fastly.enabled')) {
            $container->setAlias(AbstractReverseProxyGateway::class, FastlyReverseProxyGateway::class);
        }
    }
}
