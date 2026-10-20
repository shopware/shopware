<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

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

        $factory = new CachedSalesChannelContextFactory(
            $inner,
            new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter())),
        );

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

        $cache = new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter()));

        $factory = new CachedSalesChannelContextFactory($inner, $cache);

        $first = $factory->create('token', 'sales-channel-id', $options);

        static::assertSame($context, $first, 'a context built in this call is returned without a serialization round trip');

        $second = $factory->create('other-token', 'sales-channel-id', $options);

        static::assertNotSame($context, $second, 'a cache hit is unserialized into a fresh instance');
        static::assertSame('other-token', $second->getToken());
        static::assertSame($context->getSalesChannelId(), $second->getSalesChannelId());
    }

    public function testDoesNotCacheContextWhenTheMarkerWasInvalidatedDuringCreation(): void
    {
        $firstContext = Generator::generateSalesChannelContext();
        $secondContext = Generator::generateSalesChannelContext();
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $calls = 0;
        $inner = $this->createMock(SalesChannelContextFactory::class);
        $inner->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($firstContext, $secondContext, $cache, &$calls) {
                if ($calls++ === 0) {
                    $cache->invalidateTags([CachedSalesChannelContextFactory::ALL_TAG]);

                    return $firstContext;
                }

                return $secondContext;
            });

        $factory = new CachedSalesChannelContextFactory($inner, new InvalidationRaceAwareCache($cache));

        static::assertSame($firstContext, $factory->create('token', 'sales-channel-id'));
        static::assertSame($secondContext, $factory->create('another-token', 'sales-channel-id'));
    }
}
