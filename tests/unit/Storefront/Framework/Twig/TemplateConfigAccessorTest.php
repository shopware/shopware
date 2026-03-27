<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;

/**
 * @internal
 */
#[CoversClass(TemplateConfigAccessor::class)]
class TemplateConfigAccessorTest extends TestCase
{
    public function testConfigReturnsStaticValueWithoutCallingSystemConfig(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('get');

        $accessor = $this->createAccessor(systemConfig: $systemConfigService);

        static::assertSame(255, $accessor->config('seo.descriptionMaxLength', null));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $accessor->config('cms.revocationNoticeCmsPageId', null));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $accessor->config('cms.taxCmsPageId', null));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $accessor->config('cms.tosCmsPageId', null));
        static::assertTrue($accessor->config('confirm.revocationNotice', null));
    }

    public function testConfigFallsThroughToSystemConfigForNonStaticKey(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with('my.custom.key', 'sales-channel-id')
            ->willReturn('custom-value');

        $accessor = $this->createAccessor(systemConfig: $systemConfigService);

        static::assertSame('custom-value', $accessor->config('my.custom.key', 'sales-channel-id'));
    }

    public function testScriptsDelegatesToThemeScripts(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/app.js',
        ]);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        static::assertSame(['js/storefront/storefront.js', 'js/app.js'], $accessor->scripts());
    }

    public function testScriptsReturnsEmptyArrayWhenNoScripts(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([]);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        static::assertSame([], $accessor->scripts());
    }

    public function testComponentImportMapReturnsStoredMapDirectly(): void
    {
        // URLs are pre-computed at compile time; accessor just passes the map through.
        $storedMap = [
            'imports' => [
                'shopware' => 'https://cdn.example.com/theme/abc123/js/shopware/shopware.js',
                'Sw:Button' => 'https://cdn.example.com/theme/abc123/js/components/Sw/Button.js',
                'Sw:Product:BuyButton' => 'https://cdn.example.com/theme/abc123/js/components/Sw/Product/BuyButton.js',
            ],
        ];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn($storedMap);

        $result = $this->createAccessor(themeScripts: $themeScripts)->componentImportMap();

        static::assertSame($storedMap, $result);
    }

    public function testComponentImportMapReturnsScopesFromStoredMap(): void
    {
        $storedMap = [
            'imports' => [
                'shopware' => 'https://cdn.example.com/theme/abc123/js/shopware/shopware.js',
                'debounce' => 'https://cdn.example.com/theme/abc123/js/components/MyPlugin/vendor/debounce-abc123.js',
                'MyPlugin:Wusel:Counter' => 'https://cdn.example.com/theme/abc123/js/components/MyPlugin/Wusel/Counter.js',
            ],
            'scopes' => [
                'https://cdn.example.com/theme/abc123/js/components/MyPlugin/' => [
                    'debounce' => 'https://cdn.example.com/theme/abc123/js/components/MyPlugin/vendor/debounce-abc123.js',
                ],
            ],
        ];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn($storedMap);

        $result = $this->createAccessor(themeScripts: $themeScripts)->componentImportMap();

        static::assertSame($storedMap, $result);
    }

    public function testComponentImportMapReturnsEmptyImportsWhenNoBuildPresent(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn(null);

        $result = $this->createAccessor(themeScripts: $themeScripts)->componentImportMap();

        static::assertSame(['imports' => []], $result);
    }

    public function testThemeDelegatesToThemeConfigAccessor(): void
    {
        $context = Generator::generateSalesChannelContext();

        $themeConfigAccessor = $this->createMock(ThemeConfigValueAccessor::class);
        $themeConfigAccessor->expects($this->once())
            ->method('get')
            ->with('my-theme-key', $context, 'theme-id-123')
            ->willReturn('#ff0000');

        $accessor = $this->createAccessor(themeConfigAccessor: $themeConfigAccessor);

        static::assertSame('#ff0000', $accessor->theme('my-theme-key', $context, 'theme-id-123'));
    }

    private function createAccessor(
        ?SystemConfigService $systemConfig = null,
        ?ThemeConfigValueAccessor $themeConfigAccessor = null,
        ?ThemeScripts $themeScripts = null,
    ): TemplateConfigAccessor {
        return new TemplateConfigAccessor(
            $systemConfig ?? $this->createMock(SystemConfigService::class),
            $themeConfigAccessor ?? $this->createMock(ThemeConfigValueAccessor::class),
            $themeScripts ?? $this->createMock(ThemeScripts::class),
        );
    }
}
