<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

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

    private mixed $logger;

    protected function setUp(): void
    {
        $this->logger = new MockedCacheInvalidator();
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

        $expectedInvalidatedTags = [
            $name,
            CachedDomainLoader::CACHE_KEY,
            'translation.catalog.' . $salesChannelId,
        ];

        $this->cachedResolvedConfigLoaderInvalidator->assigned($event);

        static::assertEquals(
            $expectedInvalidatedTags,
            $this->logger->getInvalidatedTags()
        );
    }
}

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
class MockedCacheInvalidator extends CacheInvalidator
{
    /**
     * @var array<string>
     */
    private array $invalidatedTags = [];

    public function __construct()
    {
    }

    public function invalidate(array $tags, bool $force = false): void
    {
        $this->invalidatedTags = array_merge($this->invalidatedTags, $tags);
    }

    /**
     * @return array<string>
     */
    public function getInvalidatedTags(): array
    {
        return $this->invalidatedTags;
    }
}
