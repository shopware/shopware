<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator
 */
class CachedResolvedConfigLoaderInvalidatorTest extends TestCase
{
    private CachedResolvedConfigLoaderInvalidator $cachedResolvedConfigLoaderInvalidator;

    /**
     * @var CacheInvalidator&MockObject
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = $this->createMock(CacheInvalidator::class);
        $this->cachedResolvedConfigLoaderInvalidator = new CachedResolvedConfigLoaderInvalidator($this->logger);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                ThemeConfigChangedEvent::class => 'invalidate',
                ThemeAssignedEvent::class => 'assigned',
                ThemeConfigResetEvent::class => 'reset',
            ],
            CachedResolvedConfigLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testAssigned(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $event = new ThemeAssignedEvent($themeId, $salesChannelId);
        $name = 'theme-config-' . $themeId;

        $snippetSetCacheKeys = ['translation.catalog.' . $salesChannelId];

        $expectedInvalidatedTags = [
            [[$name]],
            [[CachedDomainLoader::CACHE_KEY]],
            [$snippetSetCacheKeys, true],
        ];

        $this->logger->expects(static::exactly(\count($expectedInvalidatedTags)))->method('invalidate')->withConsecutive(...$expectedInvalidatedTags);
        $this->cachedResolvedConfigLoaderInvalidator->assigned($event);
    }
}
