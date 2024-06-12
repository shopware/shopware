<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File as StorefrontPluginConfigurationFile;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ThemeScripts::class)]
class ThemeScriptsTest extends TestCase
{
    public function testGetThemeScriptsWhenNoRequestGiven(): void
    {
        $themeScripts = new ThemeScripts(
            $this->createMock(StorefrontPluginRegistry::class),
            $this->createMock(ThemeFileResolver::class),
            $this->createMock(RequestStack::class),
            new MD5ThemePathBuilder(),
            new ArrayAdapter()
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $themeScripts = new ThemeScripts(
            $this->createMock(StorefrontPluginRegistry::class),
            $this->createMock(ThemeFileResolver::class),
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $requestStack->push($request);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $pluginRegistry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([]));

        $themeScripts = new ThemeScripts(
            $pluginRegistry,
            $this->createMock(ThemeFileResolver::class),
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testLoadPaths(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'Storefront');
        $requestStack->push($request);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);

        $storefront = new StorefrontPluginConfiguration('Storefront');
        $pluginRegistry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$storefront]));

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects(static::once())
            ->method('resolveFiles')
            ->willReturn([
                ThemeFileResolver::SCRIPT_FILES => new FileCollection([
                    new StorefrontPluginConfigurationFile('foo/foo.js', [], 'foo'),
                ]),
            ]);

        $themeScripts = new ThemeScripts(
            $pluginRegistry,
            $themeFileResolver,
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
        );

        static::assertEquals(['js/foo/foo.js'], $themeScripts->getThemeScripts());
        static::assertEquals(['js/foo/foo.js'], $themeScripts->getThemeScripts());
    }

    public function testInheritsFromBase(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        // for some reason db themes the theme name is null
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'Storefront');
        $requestStack->push($request);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);

        $storefront = new StorefrontPluginConfiguration('Storefront');
        $pluginRegistry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$storefront]));

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->method('resolveFiles')
            ->willReturn([
                ThemeFileResolver::SCRIPT_FILES => new FileCollection([
                    new StorefrontPluginConfigurationFile('foo/foo.js', [], 'foo'),
                ]),
            ]);

        $themeScripts = new ThemeScripts(
            $pluginRegistry,
            $themeFileResolver,
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
        );

        static::assertEquals(['js/foo/foo.js'], $themeScripts->getThemeScripts());
    }
}
