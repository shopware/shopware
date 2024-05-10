<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ThemeScripts;
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
            new MD5ThemePathBuilder(),
            new StaticSystemConfigService(),
            $this->createMock(RequestStack::class),
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $themeScripts = new ThemeScripts(
            new MD5ThemePathBuilder(),
            new StaticSystemConfigService(),
            $requestStack
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $requestStack->push($request);

        $themeScripts = new ThemeScripts(
            new MD5ThemePathBuilder(),
            new StaticSystemConfigService(),
            $requestStack
        );

        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testLoadPaths(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'salesChannel');
        $requestStack->push($request);

        $themePathBuilder = new MD5ThemePathBuilder();
        $path = $themePathBuilder->assemblePath('salesChannel', 'Storefront');

        $systemConfig = new StaticSystemConfigService([
            ThemeScripts::SCRIPT_FILES_CONFIG_KEY . '.' . $path => ['js/foo/foo.js'],
        ]);

        $themeScripts = new ThemeScripts(
            $themePathBuilder,
            $systemConfig,
            $requestStack
        );

        static::assertEquals(['js/foo/foo.js'], $themeScripts->getThemeScripts());
    }
}
