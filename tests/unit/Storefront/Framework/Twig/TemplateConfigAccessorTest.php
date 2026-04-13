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

    public function testScriptsFiltersOutComponentScripts(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/components/Sw/Button.js',
            'js/components/Sw/Alert/index.js',
            'js/app.js',
        ]);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);
        $result = $accessor->scripts();

        static::assertSame(['js/storefront/storefront.js', 'js/app.js'], $result);
    }

    public function testScriptsReturnsAllScriptsWhenNoneAreComponents(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/app.js',
        ]);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        static::assertSame(['js/storefront/storefront.js', 'js/app.js'], $accessor->scripts());
    }

    public function testComponentImportMapBuildsTagToUrlMap(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/components/Sw/Button.js',
            'js/components/Sw/Product/BuyButton.js',
        ]);

        $packages = $this->createMock(Packages::class);
        $packages->method('getUrl')
            ->willReturnCallback(static fn (string $path) => 'http://localhost/' . $path);

        $accessor = $this->createAccessor(packages: $packages, themeScripts: $themeScripts);
        $result = $accessor->componentImportMap();

        static::assertArrayHasKey('Sw:Button', $result);
        static::assertSame('http://localhost/js/components/Sw/Button.js', $result['Sw:Button']);

        static::assertArrayHasKey('Sw:Product:BuyButton', $result);
        static::assertSame('http://localhost/js/components/Sw/Product/BuyButton.js', $result['Sw:Product:BuyButton']);

        static::assertArrayNotHasKey('storefront', $result);
    }

    public function testComponentImportMapReturnsEmptyWhenNoComponentScripts(): void
    {
        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/app.js',
        ]);

        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        static::assertSame([], $accessor->componentImportMap());
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
