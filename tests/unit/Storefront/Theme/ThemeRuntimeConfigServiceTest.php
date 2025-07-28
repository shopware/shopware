<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeRuntimeConfigService::class)]
class ThemeRuntimeConfigServiceTest extends TestCase
{
    private ThemeFileResolver&MockObject $themeFileResolver;

    private StorefrontPluginRegistry&MockObject $pluginRegistry;

    private ThemeMergedConfigBuilder&MockObject $mergedConfigBuilder;

    private ThemeRuntimeConfigStorage&MockObject $storage;

    private ThemeRuntimeConfigService $service;

    protected function setUp(): void
    {
        $this->themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $this->pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $this->mergedConfigBuilder = $this->createMock(ThemeMergedConfigBuilder::class);
        $this->storage = $this->createMock(ThemeRuntimeConfigStorage::class);

        $this->service = new ThemeRuntimeConfigService(
            $this->themeFileResolver,
            $this->pluginRegistry,
            $this->mergedConfigBuilder,
            $this->storage
        );
    }

    #[DataProvider('configProvider')]
    public function testGetRuntimeConfigByName(string $themeId, string $technicalName, ?ThemeRuntimeConfig $expectedConfig): void
    {
        // Only one storage access for two calls
        $this->storage
            ->expects($this->once())
            ->method('getByName')
            ->with($technicalName)
            ->willReturn($expectedConfig);

        // First call - should hit storage, second - use cache
        $result1 = $this->service->getRuntimeConfigByName($technicalName);
        $result2 = $this->service->getRuntimeConfigByName($technicalName);

        static::assertSame($expectedConfig, $result1);
        static::assertSame($expectedConfig, $result2);
    }

    #[DataProvider('configProvider')]
    public function testGetRuntimeConfigById(string $themeId, string $technicalName, ?ThemeRuntimeConfig $expectedConfig): void
    {
        // Only one storage access for two calls
        $this->storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($expectedConfig);

        // First call - should hit storage, second - use cache
        $result1 = $this->service->getRuntimeConfig($themeId);
        $result2 = $this->service->getRuntimeConfig($themeId);

        static::assertSame($expectedConfig, $result1);
        static::assertSame($expectedConfig, $result2);
    }

    /**
     * @return iterable<string, array{themeId: string, technicalName: string, expectedConfig: ?ThemeRuntimeConfig}>
     */
    public static function configProvider(): iterable
    {
        yield 'no record found' => [
            'themeId' => '1234567890abcdef1234567890abcde1',
            'technicalName' => 'nonexistent-theme-name',
            'expectedConfig' => null,
        ];

        yield 'config found' => [
            'themeId' => '1234567890abcdef1234567890abcdef',
            'technicalName' => 'test-theme',
            'expectedConfig' => self::createThemeRuntimeConfig(),
        ];
    }

    public function testGetResolvedRuntimeConfigReturnsNull(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';

        $this->storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn(null);

        $result = $this->service->getResolvedRuntimeConfig($themeId);

        static::assertNull($result);
    }

    public function testGetResolvedRuntimeConfigReturnsConfig(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $config = $this->createThemeRuntimeConfig();

        $this->storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $result = $this->service->getResolvedRuntimeConfig($themeId);

        static::assertSame($config, $result);
    }

    public function testGetResolvedRuntimeConfigResolvesJs(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';

        $partialConfig = $this->createThemeRuntimeConfig(
            themeId: $themeId,
            technicalName: $technicalName,
            scriptFiles: null
        );

        $this->storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($partialConfig);

        $this->pluginRegistry
            ->expects($this->once())
            ->method('getConfigurations')
            ->willReturn(
                new StorefrontPluginConfigurationCollection([
                    new StorefrontPluginConfiguration($technicalName),
                ])
            );

        $this->storage
            ->expects($this->once())
            ->method('getThemeTechnicalName')
            ->with($themeId)
            ->willReturn($technicalName);

        $scriptFilesCollection = new FileCollection([
            new File('foo/file1.js', [], 'foo'),
            new File('foo/file2.js', [], 'foo'),
        ]);

        $this->themeFileResolver
            ->expects($this->once())
            ->method('resolveFiles')
            ->willReturn([
                ThemeFileResolver::SCRIPT_FILES => $scriptFilesCollection,
            ]);

        // check that we save new config with resolved js files
        $this->storage
            ->expects($this->once())
            ->method('save')
            ->with(static::callback(function (ThemeRuntimeConfig $config) {
                return $config->scriptFiles === ['js/foo/file1.js', 'js/foo/file2.js'];
            }));

        $result = $this->service->getResolvedRuntimeConfig($themeId);

        // check that updated config is returned
        static::assertNotNull($result);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
    }

    public function testRefreshRuntimeConfig(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = true;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $this->mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        // Create a mock for the script files collection
        $scriptFilesCollection = new FileCollection([
            new File('foo/file1.js', [], 'foo'),
            new File('foo/file2.js', [], 'foo'),
        ]);

        $this->themeFileResolver
            ->expects($this->once())
            ->method('resolveFiles')
            ->with($themeConfig, $configCollection, false)
            ->willReturn([
                ThemeFileResolver::SCRIPT_FILES => $scriptFilesCollection,
            ]);

        $this->storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function ($config): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertNotNull($config->scriptFiles);
                static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $config->scriptFiles);
            });

        $result = $this->service->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertSame($technicalName, $result->technicalName);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
        static::assertSame(['key' => 'value'], $result->resolvedConfig);
        static::assertSame(['parent-theme'], $result->viewInheritance);
        static::assertSame(['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => $technicalName]], $result->iconSets);
    }

    public function testRefreshRuntimeConfigIgnoresJsExceptionWhenFilesNotRequired(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = false;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $this->mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        // Make resolveJs throw an exception
        $this->themeFileResolver
            ->expects($this->once())
            ->method('resolveFiles')
            ->willThrowException(ThemeException::themeCompileException($technicalName, 'Failed to resolve js files'));

        $this->storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function ($config): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertNull($config->scriptFiles);
            });

        $result = $this->service->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertNull($result->scriptFiles);
    }

    public function testRefreshRuntimeConfigPropagatesJsExceptionWhenFilesRequired(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = true;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        // Make resolveJs throw an exception
        $exception = ThemeException::themeCompileException($technicalName, 'Failed to resolve js files');
        $this->themeFileResolver
            ->method('resolveFiles')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->service->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);
    }

    public function testResetCaches(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $activeThemeNames = ['theme1', 'theme2'];

        $config = $this->createThemeRuntimeConfig($themeId, $technicalName);

        // storage should be called 2 times, before and after reset
        $this->storage
            ->expects($this->exactly(2))
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $this->storage
            ->expects($this->exactly(2))
            ->method('getActiveThemeNames')
            ->willReturn($activeThemeNames);

        // Populate caches
        $this->service->getRuntimeConfig($themeId);
        $this->service->getRuntimeConfigByName($technicalName);
        $this->service->getActiveThemeNames();

        // Reset all caches
        $this->service->resetCaches();

        // Load from storage
        $this->service->getRuntimeConfig($themeId);
        $this->service->getRuntimeConfigByName($technicalName);
        $this->service->getActiveThemeNames();
        $this->service->getActiveThemeNames();
    }

    public function testGetActiveThemeNames(): void
    {
        $expectedNames = ['theme1', 'theme2'];

        // Only one storage access for two calls
        $this->storage
            ->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($expectedNames);

        // First call - should hit storage, second - use cache
        $result1 = $this->service->getActiveThemeNames();
        $result2 = $this->service->getActiveThemeNames();

        static::assertSame($expectedNames, $result1);
        static::assertSame($expectedNames, $result2);
    }

    /**
     * Creates a ThemeRuntimeConfig object for testing purposes
     *
     * @param array<string>|null $scriptFiles
     * @param array<string, mixed> $resolvedConfig
     * @param array<string> $viewInheritance
     * @param array<string, array{path: string, namespace: string}> $iconSets
     */
    private static function createThemeRuntimeConfig(
        string $themeId = '1234567890abcdef1234567890abcdef',
        string $technicalName = 'test-theme',
        ?array $scriptFiles = ['file1.js', 'file2.js'],
        array $resolvedConfig = ['key' => 'value'],
        array $viewInheritance = ['parent-theme'],
        array $iconSets = ['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'test-theme']]
    ): ThemeRuntimeConfig {
        return ThemeRuntimeConfig::fromArray([
            'themeId' => $themeId,
            'technicalName' => $technicalName,
            'resolvedConfig' => $resolvedConfig,
            'viewInheritance' => $viewInheritance,
            'scriptFiles' => $scriptFiles,
            'iconSets' => $iconSets,
            'updatedAt' => new \DateTimeImmutable('2023-01-01 00:00:00'),
        ]);
    }
}
