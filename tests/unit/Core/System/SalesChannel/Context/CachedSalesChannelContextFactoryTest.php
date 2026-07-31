<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedSalesChannelContextFactory::class)]
class CachedSalesChannelContextFactoryTest extends TestCase
{
    public function testCustomerSpecificOptionsAreNotCached(): void
    {
        $context = Generator::generateSalesChannelContext();
        $options = [SalesChannelContextService::CUSTOMER_ID => 'customer-id'];

        $inner = $this->createMock(SalesChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedSalesChannelContextFactory($inner, $cache);

        static::assertSame($context, $factory->create('token', 'sales-channel-id', $options));
    }

    public function testFreshlyBuiltContextIsReturnedDirectly(): void
    {
        $context = Generator::generateSalesChannelContext();
        $options = [SalesChannelContextService::LANGUAGE_ID => 'language-id'];

        $inner = $this->createMock(SalesChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $storedValue = null;
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback) use (&$storedValue) {
                // the second call replays the stored payload like a warm cache pool would
                return $storedValue ??= $callback(static::createStub(ItemInterface::class));
            });

        $factory = new CachedSalesChannelContextFactory($inner, $cache);

        $first = $factory->create('token', 'sales-channel-id', $options);

        // the context was built in this call and is returned without a serialization round trip
        static::assertSame($context, $first);

        $second = $factory->create('other-token', 'sales-channel-id', $options);

        // a cache hit is unserialized into a fresh instance with the requested token
        static::assertNotSame($context, $second);
        static::assertSame('other-token', $second->getToken());
        static::assertSame($context->getSalesChannelId(), $second->getSalesChannelId());
    }
}
