<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedSalesChannelContextFactory::class)]
class CachedSalesChannelContextFactoryTest extends TestCase
{
    #[DataProvider('injectedAddressOptionProvider')]
    public function testContextWithInjectedAddressIsNotCached(string $option): void
    {
        $context = Generator::generateSalesChannelContext();
        $options = [$option => new CustomerAddressEntity()];

        $inner = $this->createMock(AbstractSalesChannelContextFactory::class);
        $inner->expects($this->exactly(2))
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedSalesChannelContextFactory(
            $inner,
            $cache,
            static::createStub(AbstractCacheTracer::class),
        );

        static::assertSame($context, $factory->create('token', 'sales-channel-id', $options));
        static::assertSame($context, $factory->create('token', 'sales-channel-id', $options));
    }

    public static function injectedAddressOptionProvider(): \Generator
    {
        yield 'injected billing address' => [SalesChannelContextService::BILLING_ADDRESS];
        yield 'injected shipping address' => [SalesChannelContextService::SHIPPING_ADDRESS];
    }
}
