<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
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
    private ThemeRuntimeConfigService&MockObject $themeRuntimeConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
    }

    public function testGetThemeScriptsWhenNoRequestGiven(): void
    {
        $themeScripts = new ThemeScripts(
            $this->createMock(RequestStack::class),
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
            $this->themeRuntimeConfigService,
        );

        $this->themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $themeScripts = new ThemeScripts(
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
            $this->themeRuntimeConfigService,
        );

        $this->themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        static::assertEquals([], $themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'invalid');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'sales-channel-id');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());
        $requestStack->push($request);

        $this->themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn(null);

        $themeScripts = new ThemeScripts(
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
            $this->themeRuntimeConfigService,
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

        $salesChannelContext = Generator::generateSalesChannelContext();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $requestStack->push($request);

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Storefront',
            'technicalName' => 'Storefront',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/foo/foo.js', 'js/foo/bar.js'],
            'iconSets' => [],
            'updatedAt' => new \DateTimeImmutable(),
        ]);
        $this->themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);

        $themeScripts = new ThemeScripts(
            $requestStack,
            new MD5ThemePathBuilder(),
            new ArrayAdapter(),
            $this->themeRuntimeConfigService,
        );

        static::assertEquals(['js/foo/foo.js', 'js/foo/bar.js'], $themeScripts->getThemeScripts());
    }
}
