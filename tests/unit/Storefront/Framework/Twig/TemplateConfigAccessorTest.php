<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TemplateConfigAccessor::class)]
class TemplateConfigAccessorTest extends TestCase
{
    private SystemConfigService&Stub $systemConfigService;

    private ThemeConfigValueAccessor&Stub $themeConfigAccessor;

    private ThemeScripts&Stub $themeScripts;

    private TemplateConfigAccessor $accessor;

    protected function setUp(): void
    {
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->themeConfigAccessor = static::createStub(ThemeConfigValueAccessor::class);
        $this->themeScripts = static::createStub(ThemeScripts::class);
        $this->accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [],
        );
    }

    public function testScriptsDelegatesToThemeScripts(): void
    {
        $this->themeScripts->method('getThemeScripts')->willReturn([
            'js/storefront/storefront.js',
            'js/app.js',
        ]);

        static::assertSame(['js/storefront/storefront.js', 'js/app.js'], $this->accessor->scripts());
    }

    public function testScriptsReturnsEmptyArrayWhenNoScripts(): void
    {
        $this->themeScripts->method('getThemeScripts')->willReturn([]);

        static::assertSame([], $this->accessor->scripts());
    }

    public function testImportMapResolvesStoredRelativeMapWithAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
                'Sw:Button' => '/bundles/storefront/storefront/components/Sw/Button.js',
                'Sw:Product:BuyButton' => '/bundles/storefront/storefront/components/Sw/Product/BuyButton.js',
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );
        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                    'Sw:Button' => 'https://cdn.example.com/bundles/storefront/storefront/components/Sw/Button.js',
                    'Sw:Product:BuyButton' => 'https://cdn.example.com/bundles/storefront/storefront/components/Sw/Product/BuyButton.js',
                ],
            ],
            $result
        );
    }

    public function testImportMapReturnsScopesFromStoredMap(): void
    {
        $storedMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
                'debounce' => '/bundles/myplugin/storefront/components/vendor/debounce-abc123.js',
                'MyPlugin:Wusel:Counter' => '/bundles/myplugin/storefront/components/MyPlugin/Wusel/Counter.js',
            ],
            'scopes' => [
                '/bundles/myplugin/storefront/components/MyPlugin/' => [
                    'debounce' => '/bundles/myplugin/storefront/components/vendor/debounce-abc123.js',
                ],
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );
        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                    'debounce' => 'https://cdn.example.com/bundles/myplugin/storefront/components/vendor/debounce-abc123.js',
                    'MyPlugin:Wusel:Counter' => 'https://cdn.example.com/bundles/myplugin/storefront/components/MyPlugin/Wusel/Counter.js',
                ],
                'scopes' => [
                    'https://cdn.example.com/bundles/myplugin/storefront/components/MyPlugin/' => [
                        'debounce' => 'https://cdn.example.com/bundles/myplugin/storefront/components/vendor/debounce-abc123.js',
                    ],
                ],
            ],
            $result
        );
    }

    public function testImportMapResolvesStylesWithAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
            ],
            'styles' => [
                '/bundles/storefront/storefront/components/Sw/Button.css',
                '/bundles/myplugin/storefront/components/MyPlugin/Wusel/Counter.css',
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            ['asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy())],
        );

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                ],
                'styles' => [
                    'https://cdn.example.com/bundles/storefront/storefront/components/Sw/Button.css',
                    'https://cdn.example.com/bundles/myplugin/storefront/components/MyPlugin/Wusel/Counter.css',
                ],
            ],
            $accessor->importMap()
        );
    }

    public function testImportMapKeepsNonImportKeysUnchangedWithoutAssetPackage(): void
    {
        $storedMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
            ],
            'scopes' => [
                '/bundles/myplugin/storefront/components/MyPlugin/' => [
                    'debounce' => '/bundles/myplugin/storefront/components/vendor/debounce-abc123.js',
                ],
            ],
            'styles' => [
                '/bundles/storefront/storefront/components/Sw/Button.css',
            ],
            'scripts' => [
                '/bundles/storefront/storefront/components/Sw/Button.js',
            ],
            'themeId' => 'theme-123',
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [],
        );

        static::assertSame($storedMap, $accessor->importMap());
    }

    public function testImportMapStripsQueryFromResolvedScopeKeys(): void
    {
        $storedMap = [
            'imports' => [
                '@ext/vendor' => '/bundles/myextension/storefront/components/vendor/ext-HASH.js',
            ],
            'scopes' => [
                '/bundles/myextension/storefront/components/MyExtension/' => [
                    '@ext/vendor' => '/bundles/myextension/storefront/components/vendor/ext-HASH.js',
                ],
            ],
        ];

        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'prod',
            [
                'asset' => new UrlPackage(
                    'https://cdn.example.com/base',
                    new class implements VersionStrategyInterface {
                        public function getVersion(string $path): string
                        {
                            return 'v123';
                        }

                        public function applyVersion(string $path): string
                        {
                            return $path . '?v123';
                        }
                    }
                ),
            ],
        );

        static::assertSame(
            [
                'imports' => [
                    '@ext/vendor' => 'https://cdn.example.com/base/bundles/myextension/storefront/components/vendor/ext-HASH.js?v123',
                ],
                'scopes' => [
                    'https://cdn.example.com/base/bundles/myextension/storefront/components/MyExtension/' => [
                        '@ext/vendor' => 'https://cdn.example.com/base/bundles/myextension/storefront/components/vendor/ext-HASH.js?v123',
                    ],
                ],
            ],
            $accessor->importMap()
        );
    }

    public function testImportMapReturnsEmptyImportsWhenNoBuildPresent(): void
    {
        $this->themeScripts->method('getImportMap')->willReturn(null);
        $result = $this->accessor->importMap();

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

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedConfigReturnsStaticValueWithoutCallingSystemConfig(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('get');

        $accessor = $this->createAccessor(systemConfigService: $systemConfigService);

        $result = $accessor->config('seo.descriptionMaxLength', null);

        static::assertSame(255, $result);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedConfigDelegatesToSystemConfig(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('core.basicInformation.shopName', 'sales-channel-id')
            ->willReturn('Shopware');

        $accessor = $this->createAccessor(systemConfigService: $systemConfigService);

        $result = $accessor->config('core.basicInformation.shopName', 'sales-channel-id');

        static::assertSame('Shopware', $result);
    }

    public function testImportMapPrefersDevImportMapWhenDevEnvAndFlagFilePresent(): void
    {
        $devMap = [
            'imports' => ['shopware' => 'http://localhost:5176/src/shopware.ts'],
            'styles' => ['http://localhost:5176/@fs/foo.scss'],
        ];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->method('getDevImportMap')->willReturn($devMap);
        $themeScripts->expects($this->never())->method('getImportMap');
        $accessor = $this->createAccessor(themeScripts: $themeScripts, env: 'dev');

        $result = $accessor->importMap();

        static::assertSame(
            [
                'imports' => $devMap['imports'],
                'styles' => $devMap['styles'],
                'isDevServer' => true,
            ],
            $result,
        );
    }

    public function testImportMapFallsBackToStoredMapWhenDevServerAbsent(): void
    {
        $storedMap = ['imports' => ['shopware' => '/bundles/storefront/storefront/shopware/shopware.js']];

        $this->themeScripts->method('getDevImportMap')->willReturn(null);
        $this->themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = new TemplateConfigAccessor(
            $this->systemConfigService,
            $this->themeConfigAccessor,
            $this->themeScripts,
            'dev',
            [],
        );

        static::assertSame($storedMap, $accessor->importMap());
    }

    public function testImportMapIgnoresDevImportMapOutsideDevEnvironment(): void
    {
        // Production / test environments must never return the dev server flag file
        // even if one exists on disk (stale file after a dev/prod switch).
        $storedMap = ['imports' => ['shopware' => '/bundles/storefront/storefront/shopware/shopware.js']];

        $themeScripts = $this->createMock(ThemeScripts::class);
        $themeScripts->expects($this->never())->method('getDevImportMap');
        $themeScripts->method('getImportMap')->willReturn($storedMap);
        $accessor = $this->createAccessor(themeScripts: $themeScripts);

        $result = $accessor->importMap();

        static::assertSame($storedMap, $result);
        static::assertArrayNotHasKey('isDevServer', $result);
    }

    public function testThemeCssVarsReturnsEmptyArrayWhenNoVars(): void
    {
        $this->themeConfigAccessor->method('getCssVarValues')->willReturn([]);
        static::assertSame([], $this->accessor->themeCssVars(Generator::generateSalesChannelContext(), 'theme-id'));
    }

    public function testThemeCssVarsDelegatesToAccessorWithContextAndThemeId(): void
    {
        $context = Generator::generateSalesChannelContext();

        $themeConfigAccessor = $this->createMock(ThemeConfigValueAccessor::class);
        $themeConfigAccessor->expects($this->once())
            ->method('getCssVarValues')
            ->with($context, 'theme-id-abc')
            ->willReturn(['sw-color-brand-primary' => '#0042a0']);

        $accessor = $this->createAccessor(themeConfigAccessor: $themeConfigAccessor);

        $result = $accessor->themeCssVars($context, 'theme-id-abc');

        static::assertSame(['sw-color-brand-primary' => '#0042a0'], $result);
    }

    private function createAccessor(
        ?SystemConfigService $systemConfigService = null,
        ?ThemeConfigValueAccessor $themeConfigAccessor = null,
        ?ThemeScripts $themeScripts = null,
        string $env = 'prod',
    ): TemplateConfigAccessor {
        return new TemplateConfigAccessor(
            $systemConfigService ?? $this->systemConfigService,
            $themeConfigAccessor ?? $this->themeConfigAccessor,
            $themeScripts ?? $this->themeScripts,
            $env,
        );
    }
}
