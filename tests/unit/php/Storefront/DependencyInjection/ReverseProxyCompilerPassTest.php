<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\DependencyInjection\ReverseProxyCompilerPass;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer;
use Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\DependencyInjection\ReverseProxyCompilerPass
 */
class ReverseProxyCompilerPassTest extends TestCase
{
    public function testFastlyReplaces(): void
    {
        $container = $this->getContainer();

        $container->compile();

        static::assertTrue($container->has('shopware.cache.reverse_proxy.redis'));
        static::assertTrue($container->has(ReverseProxyCache::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));
        static::assertTrue($container->has(ReverseProxyCacheClearer::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));

        /** @var DummyService $dummy */
        $dummy = $container->get(DummyService::class);
        static::assertInstanceOf(FastlyService::class, $dummy->get());
    }

    public function testFastlyReplacesAndPluginExtendsFastly(): void
    {
        $container = $this->getContainer();

        $container->register(PluginService::class, PluginService::class)
            ->setPublic(true)
            ->setDecoratedService(FastlyReverseProxyGateway::class);

        $container->compile();

        static::assertTrue($container->has('shopware.cache.reverse_proxy.redis'));
        static::assertTrue($container->has(ReverseProxyCache::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));
        static::assertTrue($container->has(ReverseProxyCacheClearer::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));

        static::assertInstanceOf(PluginService::class, $container->get(FastlyReverseProxyGateway::class));

        /** @var DummyService $dummy */
        $dummy = $container->get(DummyService::class);
        static::assertInstanceOf(PluginService::class, $dummy->get());
    }

    public function testFastlyDisabled(): void
    {
        $container = $this->getContainer();
        $container->setParameter('storefront.reverse_proxy.fastly.enabled', false);

        $container->compile();

        static::assertTrue($container->has('shopware.cache.reverse_proxy.redis'));
        static::assertTrue($container->has(ReverseProxyCache::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));
        static::assertTrue($container->has(ReverseProxyCacheClearer::class));
        static::assertTrue($container->has(FastlyReverseProxyGateway::class));

        /** @var DummyService $dummy */
        $dummy = $container->get(DummyService::class);
        static::assertInstanceOf(OriginalService::class, $dummy->get());
    }

    public function testReverseProxyDisabled(): void
    {
        $container = $this->getContainer();
        $container->setParameter('storefront.reverse_proxy.enabled', false);

        $container->compile();

        static::assertFalse($container->has('shopware.cache.reverse_proxy.redis'));
        static::assertFalse($container->has(ReverseProxyCache::class));
        static::assertFalse($container->has(FastlyReverseProxyGateway::class));
        static::assertFalse($container->has(ReverseProxyCacheClearer::class));
        static::assertFalse($container->has(FastlyReverseProxyGateway::class));
    }

    public function testReverseProxyUseXKeyVarnish(): void
    {
        $container = $this->getContainer();
        $container->setParameter('storefront.reverse_proxy.fastly.enabled', false);
        $container->setParameter('storefront.reverse_proxy.use_varnish_xkey', true);

        $container->compile();

        /** @var DummyService $dummy */
        $dummy = $container->get(DummyService::class);
        static::assertInstanceOf(VarnishService::class, $dummy->get());
    }

    public function getContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->setParameter('storefront.reverse_proxy.enabled', true);
        $container->setParameter('storefront.reverse_proxy.fastly.enabled', true);
        $container->setParameter('storefront.reverse_proxy.use_varnish_xkey', false);

        $container
            ->register('shopware.cache.reverse_proxy.redis', \stdClass::class)
            ->setPublic(true);
        $container
            ->register(ReverseProxyCache::class, ReverseProxyCache::class)
            ->setPublic(true);

        $container
            ->register(AbstractReverseProxyGateway::class, OriginalService::class)
            ->setPublic(true);

        $container
            ->register(FastlyReverseProxyGateway::class, FastlyReverseProxyGateway::class)
            ->setPublic(true);

        $container
            ->register(VarnishReverseProxyGateway::class, VarnishService::class)
            ->setPublic(true);

        $container
            ->register(ReverseProxyCacheClearer::class, ReverseProxyCacheClearer::class)
            ->setPublic(true);

        $container
            ->register(FastlyReverseProxyGateway::class, FastlyService::class)
            ->setPublic(true);

        $container
            ->register(DummyService::class, DummyService::class)
            ->setPublic(true)
            ->addArgument(new Reference(AbstractReverseProxyGateway::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));

        $container->addCompilerPass(new ReverseProxyCompilerPass());

        return $container;
    }
}

/**
 * @internal
 */
class OriginalService
{
}

/**
 * @internal
 */
class VarnishService
{
}

/**
 * @internal
 */
class FastlyService
{
}

/**
 * @internal
 */
class PluginService
{
}

/**
 * @internal
 */
class DummyService
{
    public function __construct(private readonly object $gateway)
    {
    }

    public function get(): object
    {
        return $this->gateway;
    }
}
