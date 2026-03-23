<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\AbstractResolvedConfigLoader;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;

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

    public function testGetCssVarValuesReturnsEmptyWhenThemeIdMissing(): void
    {
        $accessor = new ThemeConfigValueAccessor(
            $this->createMock(AbstractResolvedConfigLoader::class),
            $this->createMock(CacheTagCollector::class),
            $this->createMock(ThemeRuntimeConfigService::class),
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), null));
    }

    public function testGetCssVarValuesReturnsEmptyWhenRuntimeConfigServiceNotWired(): void
    {
        // Pre-v6.8 the service is nullable and resolves to [] when missing.
        $accessor = new ThemeConfigValueAccessor(
            $this->createMock(AbstractResolvedConfigLoader::class),
            $this->createMock(CacheTagCollector::class),
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesReturnsEmptyWhenRuntimeConfigMissing(): void
    {
        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->method('getRuntimeConfig')->willReturn(null);

        $accessor = new ThemeConfigValueAccessor(
            $this->createMock(AbstractResolvedConfigLoader::class),
            $this->createMock(CacheTagCollector::class),
            $runtimeConfigService,
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesEmitsSimpleStringValues(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-color-brand-primary' => ['type' => 'color'],
                'sw-font-family-base' => ['type' => 'fontFamily'],
            ],
            resolvedValues: [
                'sw-color-brand-primary' => '#0042a0',
                'sw-font-family-base' => 'Inter, sans-serif',
            ],
        );

        static::assertSame(
            [
                'sw-color-brand-primary' => '#0042a0',
                'sw-font-family-base' => 'Inter, sans-serif',
            ],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSanitizesUnsafeCharactersInPropertyNameAndValue(): void
    {
        $unsafeKey = "sw-danger;}\n{name";

        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                $unsafeKey => ['type' => 'text'],
            ],
            resolvedValues: [
                $unsafeKey => 'red; } body { color: blue',
            ],
        );

        static::assertSame(
            ['sw-dangername' => 'red\\3B  \\7D  body \\7B  color: blue'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsFieldsWithScssFalse(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-color-brand-primary' => ['type' => 'color'],
                'sw-internal-value' => ['type' => 'text', 'scss' => false],
            ],
            resolvedValues: [
                'sw-color-brand-primary' => '#0042a0',
                'sw-internal-value' => 'should not appear',
            ],
        );

        $result = $accessor->getCssVarValues($this->createContext(), 'theme-id');

        static::assertArrayHasKey('sw-color-brand-primary', $result);
        static::assertArrayNotHasKey('sw-internal-value', $result);
    }

    public function testGetCssVarValuesSkipsNullArrayAndBoolValues(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'null-value' => ['type' => 'text'],
                'array-value' => ['type' => 'text'],
                'bool-value' => ['type' => 'text'],
                'kept' => ['type' => 'color'],
            ],
            resolvedValues: [
                'null-value' => null,
                'array-value' => ['nested'],
                'bool-value' => true,
                'kept' => '#fff',
            ],
        );

        static::assertSame(
            ['kept' => '#fff'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesCastsSwitchAndCheckboxToInt(): void
    {
        // Values come from the DB as stringified numbers; bool values are filtered
        // out upstream by the `is_bool` guard, so the cast here uses numeric input.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-flag-on' => ['type' => 'switch'],
                'sw-flag-off' => ['type' => 'checkbox'],
            ],
            resolvedValues: [
                'sw-flag-on' => '1',
                'sw-flag-off' => '0',
            ],
        );

        static::assertSame(
            ['sw-flag-on' => 1, 'sw-flag-off' => 0],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesWrapsMediaUrlInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'sw-logo-desktop' => 'https://cdn.example.com/logo.png',
            ],
        );

        static::assertSame(
            ['sw-logo-desktop' => 'url(\'https://cdn.example.com/logo.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesWrapsUrlFieldInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-external-background' => ['type' => 'url'],
            ],
            resolvedValues: [
                'sw-external-background' => 'https://example.test/background.png',
            ],
        );

        static::assertSame(
            ['sw-external-background' => 'url(\'https://example.test/background.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesEscapesQuotedMediaUrlInUrlFunction(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'sw-logo-desktop' => 'https://cdn.example.com/foo\')bar\\baz.png',
            ],
        );

        static::assertSame(
            ['sw-logo-desktop' => 'url(\'https://cdn.example.com/foo\\\')bar\\\\baz.png\')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSanitizesUnsafeCharactersInMediaUrlValue(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'sw-logo-desktop' => 'https://cdn.example.com/logo.png?a=1;b=2}{',
            ],
        );

        static::assertSame(
            ['sw-logo-desktop' => 'url(\'https://cdn.example.com/logo.png?a=1\\3B b=2\\7D \\7B \')'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsUnresolvedMediaUuid(): void
    {
        // A bare UUID indicates the media ID could not be resolved to a public URL.
        // Emitting it would produce `url(<uuid>)` which is broken.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-logo-desktop' => ['type' => 'media'],
            ],
            resolvedValues: [
                'sw-logo-desktop' => Uuid::randomHex(),
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesSkipsScssColorFunctionCalls(): void
    {
        // darken()/lighten()/etc. have no CSS equivalent. They must be filtered out
        // rather than emitted as broken CSS.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'darken-literal' => ['type' => 'color'],
                'darken-var' => ['type' => 'color'],
                'lighten-call' => ['type' => 'color'],
                'hsl-with-scss-hue' => ['type' => 'color'],
                'kept' => ['type' => 'color'],
            ],
            resolvedValues: [
                'darken-literal' => 'darken(#0042a0, 5%)',
                'darken-var' => 'darken($sw-color-brand-primary, 5%)',
                'lighten-call' => 'lighten(#fff, 10%)',
                'hsl-with-scss-hue' => 'hsl(hue($sw-border-color), 20%, 30%)',
                'kept' => '#abcdef',
            ],
        );

        static::assertSame(
            ['kept' => '#abcdef'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesKeepsCssNativeFunctionsIntact(): void
    {
        // rgb/rgba/hsl/calc/linear-gradient are valid CSS — they must pass through.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-rgba' => ['type' => 'color'],
                'sw-calc' => ['type' => 'text'],
                'sw-gradient' => ['type' => 'color'],
            ],
            resolvedValues: [
                'sw-rgba' => 'rgba(0, 0, 0, 0.5)',
                'sw-calc' => 'calc(100% - 16px)',
                'sw-gradient' => 'linear-gradient(to bottom, #fff, #000)',
            ],
        );

        static::assertSame(
            [
                'sw-rgba' => 'rgba(0, 0, 0, 0.5)',
                'sw-calc' => 'calc(100% - 16px)',
                'sw-gradient' => 'linear-gradient(to bottom, #fff, #000)',
            ],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesConvertsBareScssVariableReferencesToCssVars(): void
    {
        // $sw-color-brand-primary is replaced with var(--sw-color-brand-primary)
        // so components can reference theme values without SCSS.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-color-brand-secondary' => ['type' => 'color'],
            ],
            resolvedValues: [
                'sw-color-brand-secondary' => '$sw-color-brand-primary',
            ],
        );

        static::assertSame(
            ['sw-color-brand-secondary' => 'var(--sw-color-brand-primary)'],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsScssVariableReferenceInsideFunctionCall(): void
    {
        // A `$var` inside a function that isn't in the deny-list (e.g. a custom
        // SCSS function) cannot be safely emitted, so it is skipped entirely.
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-complex' => ['type' => 'text'],
            ],
            resolvedValues: [
                'sw-complex' => 'my-function($sw-color-brand-primary, 2)',
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesSkipsScssDirectiveExpressions(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-invalid-default' => ['type' => 'text'],
                'sw-invalid-interpolation' => ['type' => 'text'],
                'sw-invalid-at-rule' => ['type' => 'text'],
                'sw-invalid-block' => ['type' => 'text'],
            ],
            resolvedValues: [
                'sw-invalid-default' => '$sw-color-brand-primary !default',
                'sw-invalid-interpolation' => '#{$sw-color-brand-primary}',
                'sw-invalid-at-rule' => '@if $sw-color-brand-primary { color: red; }',
                'sw-invalid-block' => '$sw-color-brand-primary; color: red',
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    public function testGetCssVarValuesConvertsSafeScssVariableExpressionToCssVars(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                'sw-spacing-expression' => ['type' => 'text'],
                'sw-multi-var-addition' => ['type' => 'text'],
                'sw-percent-expression' => ['type' => 'text'],
                'sw-negative-expression' => ['type' => 'text'],
            ],
            resolvedValues: [
                'sw-spacing-expression' => '$spacer * 2',
                'sw-multi-var-addition' => '$spacer + $sw-gap',
                'sw-percent-expression' => '$opacity * 100%',
                'sw-negative-expression' => '$offset * -1',
            ],
        );

        static::assertSame(
            [
                'sw-spacing-expression' => 'var(--spacer) * 2',
                'sw-multi-var-addition' => 'var(--spacer) + var(--sw-gap)',
                'sw-percent-expression' => 'var(--opacity) * 100%',
                'sw-negative-expression' => 'var(--offset) * -1',
            ],
            $accessor->getCssVarValues($this->createContext(), 'theme-id'),
        );
    }

    public function testGetCssVarValuesSkipsEntryWhenSanitizedKeyIsEmpty(): void
    {
        $accessor = $this->createAccessorWithResolvedConfig(
            fields: [
                "\n;\r{}" => ['type' => 'text'],
            ],
            resolvedValues: [
                "\n;\r{}" => 'value',
            ],
        );

        static::assertSame([], $accessor->getCssVarValues($this->createContext(), 'theme-id'));
    }

    /**
     * @param array<string, array{type: string, scss?: bool}> $fields
     * @param array<string, mixed> $resolvedValues
     */
    private function createAccessorWithResolvedConfig(array $fields, array $resolvedValues): ThemeConfigValueAccessor
    {
        $configLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $configLoader->method('load')->willReturn($resolvedValues);

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'theme-id',
            'technicalName' => 'Storefront',
            'resolvedConfig' => ['fields' => $fields],
            'viewInheritance' => [],
            'scriptFiles' => null,
            'iconSets' => [],
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->method('getRuntimeConfig')->willReturn($runtimeConfig);

        return new ThemeConfigValueAccessor(
            $configLoader,
            $this->createMock(CacheTagCollector::class),
            $runtimeConfigService,
        );
    }

    private function createContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getDomainId')->willReturn('domain-id');

        return $context;
    }
}
