<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyServiceFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReverseProxyServiceFactory::class)]
class ReverseProxyServiceFactoryTest extends TestCase
{
    public function testCreateStoreReturnsDefaultStoreWhenReverseProxyIsDisabled(): void
    {
        $defaultStore = static::createStub(StoreInterface::class);
        $reverseProxyStore = static::createStub(StoreInterface::class);

        $store = ReverseProxyServiceFactory::createStore(false, $this->createStoreLocator($defaultStore, $reverseProxyStore));

        static::assertSame($defaultStore, $store);
    }

    public function testCreateStoreReturnsReverseProxyStoreWhenReverseProxyIsEnabled(): void
    {
        $defaultStore = static::createStub(StoreInterface::class);
        $reverseProxyStore = static::createStub(StoreInterface::class);

        $store = ReverseProxyServiceFactory::createStore(true, $this->createStoreLocator($defaultStore, $reverseProxyStore));

        static::assertSame($reverseProxyStore, $store);
    }

    public function testCreateGatewayReturnsVarnishGatewayWhenFastlyIsDisabled(): void
    {
        $varnish = static::createStub(AbstractReverseProxyGateway::class);
        $fastly = static::createStub(AbstractReverseProxyGateway::class);

        $gateway = ReverseProxyServiceFactory::createGateway(false, $this->createGatewayLocator($varnish, $fastly));

        static::assertSame($varnish, $gateway);
    }

    public function testCreateGatewayReturnsFastlyGatewayWhenFastlyIsEnabled(): void
    {
        $varnish = static::createStub(AbstractReverseProxyGateway::class);
        $fastly = static::createStub(AbstractReverseProxyGateway::class);

        $gateway = ReverseProxyServiceFactory::createGateway(true, $this->createGatewayLocator($varnish, $fastly));

        static::assertSame($fastly, $gateway);
    }

    /**
     * @return ServiceLocator<StoreInterface>
     */
    private function createStoreLocator(StoreInterface $defaultStore, StoreInterface $reverseProxyStore): ServiceLocator
    {
        return new ServiceLocator([
            ReverseProxyServiceFactory::DEFAULT_STORE => static fn () => $defaultStore,
            ReverseProxyServiceFactory::REVERSE_PROXY_STORE => static fn () => $reverseProxyStore,
        ]);
    }

    /**
     * @return ServiceLocator<AbstractReverseProxyGateway>
     */
    private function createGatewayLocator(AbstractReverseProxyGateway $varnish, AbstractReverseProxyGateway $fastly): ServiceLocator
    {
        return new ServiceLocator([
            ReverseProxyServiceFactory::VARNISH_GATEWAY => static fn () => $varnish,
            ReverseProxyServiceFactory::FASTLY_GATEWAY => static fn () => $fastly,
        ]);
    }
}
