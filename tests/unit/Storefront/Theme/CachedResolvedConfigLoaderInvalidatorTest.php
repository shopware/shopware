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

    private MockedCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->cacheInvalidator = new MockedCacheInvalidator();
        $this->cachedResolvedConfigLoaderInvalidator = new CachedResolvedConfigLoaderInvalidator($this->cacheInvalidator, true);
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
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidate(): void
    {
        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test']);

        $expectedInvalidatedTags = [
            'theme-config-' . $themeId,
            'theme.test',
        ];

        $this->cachedResolvedConfigLoaderInvalidator->invalidate($event);

        static::assertEquals(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidateDisabledFineGrained(): void
    {
        $this->cachedResolvedConfigLoaderInvalidator = new CachedResolvedConfigLoaderInvalidator($this->cacheInvalidator, false);

        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test']);

        $expectedInvalidatedTags = [
            'shopware.theme',
        ];

        $this->cachedResolvedConfigLoaderInvalidator->invalidate($event);

        static::assertEquals(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
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
