<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    private FilesystemOperator&MockObject $tempFilesystem;

    private LoggerInterface&MockObject $logger;

    private ThemeScripts $themeScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $this->tempFilesystem = $this->createMock(FilesystemOperator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestStack = new RequestStack();
        $this->themeScripts = new ThemeScripts(
            $this->requestStack,
            $this->themeRuntimeConfigService,
            $this->tempFilesystem,
            $this->logger,
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

    public function testGetImportMapReturnsNullWhenNoRequest(): void
    {
        $this->themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');

        static::assertNull($this->themeScripts->getImportMap());
    }

    public function testGetImportMapReturnsNullWhenNoBuildPresent(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());
        $this->requestStack->push($request);

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Storefront',
            'technicalName' => 'Storefront',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/storefront/storefront.js'],
            'iconSets' => [],
            // importMap deliberately absent (no Vite build yet)
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $this->themeRuntimeConfigService->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);

        static::assertNull($this->themeScripts->getImportMap());
    }

    public function testGetImportMapReturnsStoredMap(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'Storefront');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'Storefront');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());
        $this->requestStack->push($request);

        $importMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
                'Sw:Button' => 'js/components/Sw/Button.js',
            ],
            'scopes' => [
                'js/components/MyPlugin/' => [
                    'debounce' => 'js/components/MyPlugin/vendor/debounce-abc123.js',
                ],
            ],
        ];

        $themeRuntimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => 'Storefront',
            'technicalName' => 'Storefront',
            'resolvedConfig' => [],
            'viewInheritance' => [],
            'scriptFiles' => ['js/storefront/storefront.js'],
            'iconSets' => [],
            'importMap' => $importMap,
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        $this->themeRuntimeConfigService->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);

        static::assertSame($importMap, $this->themeScripts->getImportMap());
    }

    public function testGetDevImportMapReturnsNullWhenFlagFileAbsent(): void
    {
        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(false);

        static::assertNull($this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsParsedMapWhenFlagFilePresent(): void
    {
        $devMap = ['imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts']];

        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullForInvalidJson(): void
    {
        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn('not json {{{');
        $this->logger->expects($this->once())->method('warning');

        static::assertNull($this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullWhenRequestThemeIdDoesNotMatchDevThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'request-theme');
        $this->requestStack->push($request);

        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn((string) json_encode([
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'themeId' => 'dev-theme',
        ]));
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Storefront dev import map skipped due to theme mismatch.',
                [
                    'requestThemeId' => 'request-theme',
                    'devThemeId' => 'dev-theme',
                    'path' => 'cache/storefront_components.dev.json',
                ]
            );

        static::assertNull($this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenRequestThemeIdMatchesDevThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'storefront');
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'themeId' => 'storefront',
        ];

        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenRequestThemeIdIsMissing(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'themeId' => 'storefront',
        ];

        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsMapWhenThemeIdIsNotString(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'storefront');
        $this->requestStack->push($request);

        $devMap = [
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'themeId' => 123,
        ];

        $this->tempFilesystem->method('fileExists')->with('cache/storefront_components.dev.json')->willReturn(true);
        $this->tempFilesystem->method('read')->with('cache/storefront_components.dev.json')->willReturn((string) json_encode($devMap));
        $this->logger->expects($this->never())->method('debug');

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }
}
