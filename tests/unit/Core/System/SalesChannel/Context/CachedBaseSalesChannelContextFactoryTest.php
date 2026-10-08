<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedBaseSalesChannelContextFactory::class)]
class CachedBaseSalesChannelContextFactoryTest extends TestCase
{
    public function testDoesNotCacheContextWhenTheMarkerWasInvalidatedDuringCreation(): void
    {
        $firstContext = static::createStub(BaseSalesChannelContext::class);
        $secondContext = static::createStub(BaseSalesChannelContext::class);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $calls = 0;
        $decorated = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $decorated->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($firstContext, $secondContext, $cache, &$calls) {
                if ($calls++ === 0) {
                    $cache->invalidateTags([CachedSalesChannelContextFactory::ALL_TAG]);

                    return $firstContext;
                }

                return $secondContext;
            });

        $factory = new CachedBaseSalesChannelContextFactory($decorated, new InvalidationRaceAwareCache($cache));

        static::assertSame($firstContext, $factory->create('sales-channel-id'));
        static::assertSame($secondContext, $factory->create('sales-channel-id'));
    }
}
