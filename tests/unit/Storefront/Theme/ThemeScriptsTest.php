<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ThemeScripts::class)]
class ThemeScriptsTest extends TestCase
{
    private RequestStack $requestStack;

    private ThemeRuntimeConfigService&MockObject $themeRuntimeConfigService;

    private ThemeScripts $themeScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $this->requestStack = new RequestStack();
        $this->themeScripts = new ThemeScripts(
            $this->requestStack,
            $this->themeRuntimeConfigService,
        );
    }

    public function testGetThemeScriptsWhenNoRequestGiven(): void
    {
        $this->themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        static::assertSame([], $this->themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $this->requestStack->push(new Request());

        $this->themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        static::assertSame([], $this->themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'invalid');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'sales-channel-id');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());
        $this->requestStack->push($request);

        $this->themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn(null);

        static::assertSame([], $this->themeScripts->getThemeScripts());
    }

    public function testLoadPaths(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'Storefront');

        $salesChannelContext = Generator::generateSalesChannelContext();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $this->requestStack->push($request);

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

        static::assertSame(['js/foo/foo.js', 'js/foo/bar.js'], $this->themeScripts->getThemeScripts());
    }
}
