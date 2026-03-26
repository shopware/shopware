<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\Asset\Packages;

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

    public function testComponentImportMapConvertsRelativePathsToFullUrls(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn([
            'imports' => [
                'shopware' => 'js/shopware/shopware.js',
                'Sw:Button' => 'js/components/Sw/Button.js',
                'Sw:Product:BuyButton' => 'js/components/Sw/Product/BuyButton.js',
            ],
        ]);

        $packages = $this->createMock(Packages::class);
        $packages->method('getUrl')
            ->willReturnCallback(static fn (string $path) => 'https://cdn.example.com/' . $path);

        $accessor = $this->createAccessor(packages: $packages, themeScripts: $themeScripts);
        $result = $accessor->componentImportMap();

        static::assertArrayHasKey('imports', $result);
        static::assertSame('https://cdn.example.com/js/shopware/shopware.js', $result['imports']['shopware']);
        static::assertSame('https://cdn.example.com/js/components/Sw/Button.js', $result['imports']['Sw:Button']);
        static::assertSame('https://cdn.example.com/js/components/Sw/Product/BuyButton.js', $result['imports']['Sw:Product:BuyButton']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testComponentImportMapConvertsVendorScopesToFullUrls(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn([
            'imports' => [
                'shopware' => 'js/shopware/shopware.js',
                'debounce' => 'js/components/MyPlugin/vendor/debounce-abc123.js',
                'MyPlugin:Wusel:Counter' => 'js/components/MyPlugin/Wusel/Counter.js',
            ],
            'scopes' => [
                'js/components/MyPlugin/' => [
                    'debounce' => 'js/components/MyPlugin/vendor/debounce-abc123.js',
                ],
            ],
        ]);

        $packages = $this->createMock(Packages::class);
        $packages->method('getUrl')
            ->willReturnCallback(static fn (string $path) => 'https://cdn.example.com/' . $path);

        $accessor = $this->createAccessor(packages: $packages, themeScripts: $themeScripts);
        $result = $accessor->componentImportMap();

        static::assertArrayHasKey('scopes', $result);
        $scopeUrl = 'https://cdn.example.com/js/components/MyPlugin/';
        static::assertArrayHasKey($scopeUrl, $result['scopes']);
        static::assertSame(
            'https://cdn.example.com/js/components/MyPlugin/vendor/debounce-abc123.js',
            $result['scopes'][$scopeUrl]['debounce']
        );
    }

    public function testComponentImportMapReturnsEmptyImportsWhenNoBuildPresent(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn(null);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);
        $result = $accessor->componentImportMap();

        static::assertSame(['imports' => []], $result);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testComponentImportMapOmitsScopesWhenEmpty(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getComponentImportMap')->willReturn([
            'imports' => ['Sw:Button' => 'js/components/Sw/Button.js'],
        ]);

        $packages = $this->createMock(Packages::class);
        $packages->method('getUrl')->willReturnCallback(static fn (string $p) => 'https://cdn.example.com/' . $p);

        $accessor = $this->createAccessor(packages: $packages, themeScripts: $themeScripts);
        $result = $accessor->componentImportMap();

        static::assertArrayHasKey('imports', $result);
        static::assertArrayNotHasKey('scopes', $result);
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
        ?Packages $packages = null,
    ): TemplateConfigAccessor {
        return new TemplateConfigAccessor(
            $systemConfig ?? $this->createMock(SystemConfigService::class),
            $themeConfigAccessor ?? $this->createMock(ThemeConfigValueAccessor::class),
            $themeScripts ?? $this->createMock(ThemeScripts::class),
            $packages ?? $this->createMock(Packages::class),
        );
    }
}
