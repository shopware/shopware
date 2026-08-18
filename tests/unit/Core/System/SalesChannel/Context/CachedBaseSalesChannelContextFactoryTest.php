<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\AtsContextCacheTrace;
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
    use EnvTestBehaviour;

    public function testBypassesCacheWhenAtsIsRunning(): void
    {
        $this->setEnvVars(['ATS_RUNNING' => '1']);
        $context = static::createStub(BaseSalesChannelContext::class);
        $decorated = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $decorated->expects($this->once())
            ->method('create')
            ->with('sales-channel-id', ['languageId' => 'language-id'])
            ->willReturn($context);

        $factory = new CachedBaseSalesChannelContextFactory(
            $decorated,
            new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter()), static::createStub(AtsContextCacheTrace::class)),
            static::createStub(AtsContextCacheTrace::class),
        );

        static::assertSame($context, $factory->create('sales-channel-id', ['languageId' => 'language-id']));
    }

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

        $factory = new CachedBaseSalesChannelContextFactory($decorated, new InvalidationRaceAwareCache($cache, static::createStub(AtsContextCacheTrace::class)), static::createStub(AtsContextCacheTrace::class));

        static::assertSame($firstContext, $factory->create('sales-channel-id'));
        static::assertSame($secondContext, $factory->create('sales-channel-id'));
    }
}
