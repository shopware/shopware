<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\AbstractResolvedConfigLoader;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;

/**
 * @internal
 */
#[CoversClass(ThemeConfigValueAccessor::class)]
class ThemeConfigValueAccessorTest extends TestCase
{
    public function testGetWithoutThemeIdOnV68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // When no theme ID is provided and v6.8 flag is inactive,
        // it should return the deprecated default breakpoint values
        $result = $accessor->get('breakpoint.xs', $context, null);

        static::assertSame(0, $result);
        static::assertSame(576, $accessor->get('breakpoint.sm', $context, null));
        static::assertSame(768, $accessor->get('breakpoint.md', $context, null));
        static::assertSame(992, $accessor->get('breakpoint.lg', $context, null));
        static::assertSame(1200, $accessor->get('breakpoint.xl', $context, null));
        static::assertSame(1400, $accessor->get('breakpoint.xxl', $context, null));
    }

    public function testGetWithoutThemeIdPostV68(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // When no theme ID is provided and v6.8 flag is active,
        // it should return null (no deprecated breakpoint defaults)
        $result = $accessor->get('breakpoint.xs', $context, null);

        static::assertNull($result);
    }

    public function testGetWithThemeId(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            'sw-breakpoint-xs' => 0,
            'sw-breakpoint-sm' => 576,
            'sw-breakpoint-md' => 768,
            'sw-breakpoint-lg' => 992,
            'sw-breakpoint-xl' => 1200,
            'sw-breakpoint-xxl' => 1400,
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())->method('addTag');

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        $result = $accessor->get('breakpoint.xs', $context, 'theme-id');

        static::assertSame(0, $result);
    }

    public function testGetWithThemeIdAndCustomBreakpoints(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            'sw-breakpoint-xs' => 100,
            'sw-breakpoint-sm' => 600,
            'sw-breakpoint-md' => 800,
            'sw-breakpoint-lg' => 1000,
            'sw-breakpoint-xl' => 1300,
            'sw-breakpoint-xxl' => 1500,
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // Test all breakpoint sizes
        static::assertSame(100, $accessor->get('breakpoint.xs', $context, 'theme-id'));
        static::assertSame(600, $accessor->get('breakpoint.sm', $context, 'theme-id'));
        static::assertSame(800, $accessor->get('breakpoint.md', $context, 'theme-id'));
        static::assertSame(1000, $accessor->get('breakpoint.lg', $context, 'theme-id'));
        static::assertSame(1300, $accessor->get('breakpoint.xl', $context, 'theme-id'));
        static::assertSame(1500, $accessor->get('breakpoint.xxl', $context, 'theme-id'));
    }

    public function testGetWithThemeIdAndMissingBreakpoints(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([
            // No breakpoint configuration provided
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // When breakpoints are missing, should fall back to defaults
        static::assertSame(0, $accessor->get('breakpoint.xs', $context, 'theme-id'));
        static::assertSame(576, $accessor->get('breakpoint.sm', $context, 'theme-id'));
        static::assertSame(768, $accessor->get('breakpoint.md', $context, 'theme-id'));
        static::assertSame(992, $accessor->get('breakpoint.lg', $context, 'theme-id'));
        static::assertSame(1200, $accessor->get('breakpoint.xl', $context, 'theme-id'));
        static::assertSame(1400, $accessor->get('breakpoint.xxl', $context, 'theme-id'));
    }

    public function testGetCachesResults(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->expects($this->once())->method('load')->willReturn([
            'sw-breakpoint-xs' => 0,
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // First call should load config
        $result1 = $accessor->get('breakpoint.xs', $context, 'theme-id');

        // Second call should use cached config (load should only be called once)
        $result2 = $accessor->get('breakpoint.xs', $context, 'theme-id');

        static::assertSame($result1, $result2);
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        $result = $accessor->get('non.existent.key', $context, 'theme-id');

        static::assertNull($result);
    }

    public function testGetWithAssetsConfig(): void
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $accessor = new ThemeConfigValueAccessor($configLoader, $cacheTagCollector);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        // Test that assets configuration is properly set
        $cssList = $accessor->get('assets.css', $context, 'theme-id');
        $jsList = $accessor->get('assets.js', $context, 'theme-id');

        static::assertIsArray($cssList);
        static::assertContains('/css/all.css', $cssList);
        static::assertIsArray($jsList);
        static::assertContains('/js/all.js', $jsList);
    }
}
