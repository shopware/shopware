<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
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
#[Package('discovery')]
#[CoversClass(ThemeScripts::class)]
class ThemeScriptsTest extends TestCase
{
    private RequestStack $requestStack;

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    private FilesystemOperator&Stub $tempFilesystem;

    private LoggerInterface&Stub $logger;

    private ThemeScripts $themeScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->tempFilesystem = static::createStub(FilesystemOperator::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->requestStack = new RequestStack();
        $this->themeScripts = $this->createThemeScripts();
    }

    public function testGetThemeScriptsWhenNoRequestGiven(): void
    {
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);
        static::assertSame([], $themeScripts->getThemeScripts());
    }

    public function testGetThemeScriptsWhenAdminRequest(): void
    {
        $this->requestStack->push(new Request());

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);
        static::assertSame([], $themeScripts->getThemeScripts());
    }

    public function testNotExistingTheme(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'invalid');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'invalid');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'sales-channel-id');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());
        $this->requestStack->push($request);

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn(null);
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertSame([], $themeScripts->getThemeScripts());
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
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())->method('getResolvedRuntimeConfig')->willReturn($themeRuntimeConfig);
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertSame(['js/foo/foo.js', 'js/foo/bar.js'], $themeScripts->getThemeScripts());
    }

    public function testGetImportMapReturnsNullWhenNoRequest(): void
    {
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getResolvedRuntimeConfig');
        $themeScripts = $this->createThemeScripts($themeRuntimeConfigService);

        static::assertNull($themeScripts->getImportMap());
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
        $this->tempFilesystem->method('fileExists')->willReturn(false);

        static::assertNull($this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsParsedMapWhenFlagFilePresent(): void
    {
        $devMap = ['imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts']];

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

        static::assertSame($devMap, $this->themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullForInvalidJson(): void
    {
        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn('not json {{{');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertNull($themeScripts->getDevImportMap());
    }

    public function testGetDevImportMapReturnsNullWhenRequestThemeIdDoesNotMatchDevThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'request-theme');
        $this->requestStack->push($request);

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode([
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'themeId' => 'dev-theme',
        ]));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Storefront dev import map skipped due to theme mismatch.',
                [
                    'requestThemeId' => 'request-theme',
                    'devThemeId' => 'dev-theme',
                    'path' => 'cache/storefront_components.dev.json',
                ]
            );
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertNull($themeScripts->getDevImportMap());
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

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

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

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));

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

        $this->tempFilesystem->method('fileExists')->willReturn(true);
        $this->tempFilesystem->method('read')->willReturn((string) json_encode($devMap));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');
        $themeScripts = $this->createThemeScripts(logger: $logger);

        static::assertSame($devMap, $themeScripts->getDevImportMap());
    }

    private function createThemeScripts(
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
        ?LoggerInterface $logger = null,
    ): ThemeScripts {
        return new ThemeScripts(
            $this->requestStack,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
            $this->tempFilesystem,
            $logger ?? $this->logger,
        );
    }
}
