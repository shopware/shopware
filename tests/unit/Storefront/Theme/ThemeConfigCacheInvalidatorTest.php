<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\ThemeConfigCacheInvalidator;

/**
 * @internal
 */
#[CoversClass(ThemeConfigCacheInvalidator::class)]
class ThemeConfigCacheInvalidatorTest extends TestCase
{
    private ThemeConfigCacheInvalidator $themeConfigCacheInvalidator;

    private MockedCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->cacheInvalidator = new MockedCacheInvalidator();
        $this->themeConfigCacheInvalidator = new ThemeConfigCacheInvalidator($this->cacheInvalidator);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ThemeConfigChangedEvent::class => 'invalidate',
                ThemeAssignedEvent::class => 'assigned',
                ThemeConfigResetEvent::class => 'reset',
            ],
            ThemeConfigCacheInvalidator::getSubscribedEvents()
        );
    }

    public function testAssigned(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $event = new ThemeAssignedEvent($themeId, $salesChannelId);
        $name = 'theme-config-' . $themeId;

        $this->themeConfigCacheInvalidator->assigned($event);

        $expectedInvalidatedTags = [
            $name,
            CachedDomainLoader::CACHE_KEY,
            Translator::tag($salesChannelId),
        ];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidate(): void
    {
        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test']);

        $this->themeConfigCacheInvalidator->invalidate($event);

        $expectedInvalidatedTags = ['theme-config-' . $themeId];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidateDisabledFineGrained(): void
    {
        $this->themeConfigCacheInvalidator = new ThemeConfigCacheInvalidator($this->cacheInvalidator);

        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test']);

        $this->themeConfigCacheInvalidator->invalidate($event);

        $expectedInvalidatedTags = ['theme-config-' . $themeId];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }
}
