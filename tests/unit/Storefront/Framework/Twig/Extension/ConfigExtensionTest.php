<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Shopware\Storefront\Framework\Twig\Extension\ConfigExtension;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(ConfigExtension::class)]
class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $extension = new ConfigExtension($this->createMock(TemplateConfigAccessor::class));
        $functions = $extension->getFunctions();

        static::assertCount(5, $functions);

        $names = array_map(static fn (TwigFunction $f) => $f->getName(), $functions);
        static::assertContains('config', $names);
        static::assertContains('theme_config', $names);
        static::assertContains('theme_scripts', $names);
        static::assertContains('import_map', $names);
        static::assertContains('theme_css_vars', $names);
    }

    public function testConfigExtractsSalesChannelIdFromSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('config')
            ->with('my.key', $salesChannelContext->getSalesChannelId())
            ->willReturn('value');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['context' => $salesChannelContext], 'my.key');

        static::assertSame('value', $result);
    }

    public function testConfigUsesSalesChannelContextFallback(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', TestDefaults::SALES_CHANNEL)
            ->willReturn('Shopware');

        $extension = new ConfigExtension($config);

        static::assertSame('Shopware', $extension->config([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ], 'core.basicInformation.shopName'));
    }

    public function testConfigExtractsSalesChannelIdFromSalesChannelEntity(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('channel-id-abc');
        $salesChannel->setUniqueIdentifier('channel-id-abc');

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('config')
            ->with('my.key', 'channel-id-abc')
            ->willReturn('value');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['salesChannel' => $salesChannel], 'my.key');

        static::assertSame('value', $result);
    }

    public function testConfigPassesNullSalesChannelIdWhenNoContextPresent(): void
    {
        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('config')
            ->with('my.key', null)
            ->willReturn(42);

        $extension = new ConfigExtension($accessor);
        $result = $extension->config([], 'my.key');

        static::assertSame(42, $result);
    }

    public function testThemeExtractsContextAndThemeId(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('theme')
            ->with('color', $salesChannelContext, 'theme-id-xyz')
            ->willReturn('#abc');

        $extension = new ConfigExtension($accessor);
        $result = $extension->theme(
            ['context' => $salesChannelContext, 'themeId' => 'theme-id-xyz'],
            'color'
        );

        static::assertSame('#abc', $result);
    }

    public function testThemeConfigUsesSalesChannelContextFallback(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('theme')
            ->with('sw-logo-desktop', $salesChannelContext, $themeId)
            ->willReturn('logo.png');

        $extension = new ConfigExtension($config);

        static::assertSame('logo.png', $extension->theme([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
            'themeId' => $themeId,
        ], 'sw-logo-desktop'));
    }

    public function testThemeThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension($this->createMock(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

        $extension->theme([], 'color');
    }

    public function testThemeThrowsWhenContextIsNotSalesChannelContext(): void
    {
        $extension = new ConfigExtension($this->createMock(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

        $extension->theme(['context' => 'not-a-context-object'], 'color');
    }

    public function testScriptsDelegatesToAccessor(): void
    {
        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('scripts')
            ->willReturn(['js/app.js']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(['js/app.js'], $extension->scripts());
    }

    public function testImportMapDelegatesToAccessor(): void
    {
        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('importMap')
            ->willReturn(['Sw:Button' => 'http://localhost/js/components/Sw/Button.js']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(
            ['Sw:Button' => 'http://localhost/js/components/Sw/Button.js'],
            $extension->importMap()
        );
    }

    public function testThemeCssVarsDelegatesToAccessor(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('themeCssVars')
            ->with($salesChannelContext, 'theme-id-xyz')
            ->willReturn(['sw-color-brand-primary' => '#0042a0']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(
            ['sw-color-brand-primary' => '#0042a0'],
            $extension->themeCssVars(['context' => $salesChannelContext, 'themeId' => 'theme-id-xyz'])
        );
    }

    public function testThemeCssVarsThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension($this->createMock(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

        $extension->themeCssVars([]);
    }
}
