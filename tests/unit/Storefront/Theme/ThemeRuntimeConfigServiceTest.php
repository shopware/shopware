<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
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
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeRuntimeConfigService::class)]
class ThemeRuntimeConfigServiceTest extends TestCase
{
    private ThemeFileResolver&Stub $themeFileResolver;

    private StorefrontPluginRegistry&Stub $pluginRegistry;

    private ThemeMergedConfigBuilder&Stub $mergedConfigBuilder;

    private ThemeRuntimeConfigStorage&Stub $storage;

    private ThemeRuntimeConfigService $service;

    protected function setUp(): void
    {
        $this->themeFileResolver = static::createStub(ThemeFileResolver::class);
        $this->pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $this->mergedConfigBuilder = static::createStub(ThemeMergedConfigBuilder::class);
        $this->storage = static::createStub(ThemeRuntimeConfigStorage::class);

        $this->service = new ThemeRuntimeConfigService(
            $this->themeFileResolver,
            $this->pluginRegistry,
            $this->mergedConfigBuilder,
            $this->storage,
            new NativeClock()
        );
    }

    #[DataProvider('configProvider')]
    public function testGetRuntimeConfigByName(string $themeId, string $technicalName, ?ThemeRuntimeConfig $expectedConfig): void
    {
        // Only one storage access for two calls
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getByName')
            ->with($technicalName)
            ->willReturn($expectedConfig);

        $service = $this->createService(storage: $storage);

        // First call - should hit storage, second - use cache
        $result1 = $service->getRuntimeConfigByName($technicalName);
        $result2 = $service->getRuntimeConfigByName($technicalName);

        static::assertSame($expectedConfig, $result1);
        static::assertSame($expectedConfig, $result2);
    }

    #[DataProvider('configProvider')]
    public function testGetRuntimeConfigById(string $themeId, string $technicalName, ?ThemeRuntimeConfig $expectedConfig): void
    {
        // Only one storage access for two calls
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($expectedConfig);

        $service = $this->createService(storage: $storage);

        // First call - should hit storage, second - use cache
        $result1 = $service->getRuntimeConfig($themeId);
        $result2 = $service->getRuntimeConfig($themeId);

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

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn(null);

        $result = $this->createService(storage: $storage)->getResolvedRuntimeConfig($themeId);

        static::assertNull($result);
    }

    public function testGetResolvedRuntimeConfigReturnsConfig(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $config = $this->createThemeRuntimeConfig();

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $result = $this->createService(storage: $storage)->getResolvedRuntimeConfig($themeId);

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

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // Called twice: once in getRuntimeConfig(), once in refreshRuntimeConfig() to preserve importMap.
        $storage
            ->expects($this->exactly(2))
            ->method('getById')
            ->with($themeId)
            ->willReturn($partialConfig);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $pluginRegistry
            ->expects($this->once())
            ->method('getConfigurations')
            ->willReturn(
                new StorefrontPluginConfigurationCollection([
                    new StorefrontPluginConfiguration($technicalName),
                ])
            );

        $storage
            ->expects($this->once())
            ->method('getThemeTechnicalName')
            ->with($themeId)
            ->willReturn($technicalName);

        $storage
            ->method('getOwnThemeTechnicalName')
            ->with($themeId)
            ->willReturn($technicalName);

        $scriptFilesCollection = new FileCollection([
            new File('foo/file1.js', [], 'foo'),
            new File('foo/file2.js', [], 'foo'),
        ]);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->willReturn($scriptFilesCollection);

        // check that we save new config with resolved js files
        $storage
            ->expects($this->once())
            ->method('save')
            ->with(static::callback(static function (ThemeRuntimeConfig $config) {
                return $config->scriptFiles === ['js/foo/file1.js', 'js/foo/file2.js'];
            }));

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            pluginRegistry: $pluginRegistry,
            storage: $storage,
        )->getResolvedRuntimeConfig($themeId);

        // check that updated config is returned
        static::assertNotNull($result);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
    }

    /**
     * A theme copy must be persisted with a NULL technical name, not its parent's, so copies do not
     * collide on the unique technical_name index.
     */
    public function testGenerateRuntimeConfigForCopyStoresNullTechnicalName(): void
    {
        $copyId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $parentTechnicalName = 'zenitPlatformGravity';

        $themeConfig = new StorefrontPluginConfiguration($parentTechnicalName);
        $themeConfig->setIconSets(['set1' => 'path/to/icons']);

        $pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $pluginRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$themeConfig]));

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage->method('getById')->with($copyId)->willReturn(null);
        // getThemeTechnicalName() inherits the parent's name; getOwnThemeTechnicalName() is NULL for the copy.
        $storage->method('getThemeTechnicalName')->with($copyId)->willReturn($parentTechnicalName);
        $storage->method('getOwnThemeTechnicalName')->with($copyId)->willReturn(null);
        $storage->method('getCopiesIds')->with($copyId)->willReturn([]);

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn(['key' => 'value']);
        $this->themeFileResolver->method('resolveScriptFiles')->willReturn(new FileCollection());

        $saved = null;
        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use (&$saved): void {
                $saved = $config;
            });

        $result = $this->createService(
            pluginRegistry: $pluginRegistry,
            storage: $storage,
        )->getRuntimeConfig($copyId);

        static::assertNotNull($saved);
        static::assertNull($saved->technicalName, 'Theme copy must be stored with a NULL technical name');
        static::assertSame($copyId, $saved->themeId);
        static::assertSame(
            ['set1' => ['path' => 'path/to/icons', 'namespace' => $parentTechnicalName]],
            $saved->iconSets
        );

        static::assertNotNull($result);
        static::assertNull($result->technicalName);
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

        $mergedConfigBuilder = $this->createMock(ThemeMergedConfigBuilder::class);
        $mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        $scriptFilesCollection = new FileCollection([
            new File('foo/file1.js', [], 'foo'),
            new File('foo/file2.js', [], 'foo'),
        ]);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->with($themeConfig, $configCollection, false)
            ->willReturn($scriptFilesCollection);

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // No existing config stored — getById called to check for preserved importMap.
        $storage->method('getById')->willReturn(null);
        $storage->method('getOwnThemeTechnicalName')->with($themeId)->willReturn($technicalName);

        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function ($config) use ($technicalName): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertSame($technicalName, $config->technicalName);
                static::assertNotNull($config->scriptFiles);
                static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $config->scriptFiles);
                static::assertNull($config->importMap);
            });

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            mergedConfigBuilder: $mergedConfigBuilder,
            storage: $storage,
        )->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertSame($technicalName, $result->technicalName);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
        static::assertSame(['key' => 'value'], $result->resolvedConfig);
        static::assertSame(['parent-theme'], $result->viewInheritance);
        static::assertSame(['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => $technicalName]], $result->iconSets);
        static::assertNull($result->importMap);
    }

    public function testRefreshRuntimeConfigStoresComponentImportMapWhenProvided(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig]);

        $importMap = [
            'imports' => [
                'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
                'Sw:Button' => 'js/components/Sw/Button.js',
            ],
        ];

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn([]);

        $this->themeFileResolver->method('resolveScriptFiles')
            ->willReturn(new FileCollection());

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use ($importMap): void {
                static::assertSame($importMap, $config->importMap);
            });

        $result = $this->createService(storage: $storage)->refreshRuntimeConfig(
            $themeId,
            $themeConfig,
            $context,
            false,
            $configCollection,
            $importMap,
        );

        static::assertSame($importMap, $result->importMap);
    }

    public function testRefreshRuntimeConfigPreservesComponentImportMapFromStorageOnNonCompileRefresh(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig]);

        $existingImportMap = [
            'imports' => ['Sw:Button' => 'js/components/Sw/Button.js'],
        ];

        $existingConfig = $this->createThemeRuntimeConfig(
            themeId: $themeId,
            technicalName: $technicalName,
        );
        // Set importMap via `with()` since createThemeRuntimeConfig doesn't expose it as param.
        $existingConfig = $existingConfig->with(['importMap' => $existingImportMap]);

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn([]);
        $this->themeFileResolver->method('resolveScriptFiles')->willReturn(new FileCollection());

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // Storage is read to retrieve the existing importMap; no explicit import map passed.
        $storage->method('getById')->willReturn($existingConfig);

        $storage->expects($this->once())->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use ($existingImportMap): void {
                static::assertSame($existingImportMap, $config->importMap);
            });

        // Pass null for importMap (non-compile refresh).
        $result = $this->createService(storage: $storage)->refreshRuntimeConfig(
            $themeId,
            $themeConfig,
            $context,
            false,
            $configCollection,
            null,
        );

        static::assertSame($existingImportMap, $result->importMap);
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

        $mergedConfigBuilder = $this->createMock(ThemeMergedConfigBuilder::class);
        $mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->willThrowException(ThemeException::themeCompileException($technicalName, 'Failed to resolve js files'));

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage->method('getById')->willReturn(null);

        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function ($config): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertNull($config->scriptFiles);
                static::assertNull($config->importMap);
            });

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            mergedConfigBuilder: $mergedConfigBuilder,
            storage: $storage,
        )->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertNull($result->scriptFiles);
        static::assertNull($result->importMap);
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
            ->method('resolveScriptFiles')
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
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->exactly(2))
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $storage
            ->expects($this->exactly(2))
            ->method('getActiveThemeNames')
            ->willReturn($activeThemeNames);

        $service = $this->createService(storage: $storage);

        // Populate caches
        $service->getRuntimeConfig($themeId);
        $service->getRuntimeConfigByName($technicalName);
        $service->getActiveThemeNames();

        // Reset all caches
        $service->resetCaches();

        // Load from storage
        $service->getRuntimeConfig($themeId);
        $service->getRuntimeConfigByName($technicalName);
        $service->getActiveThemeNames();
        $service->getActiveThemeNames();
    }

    public function testGetActiveThemeNames(): void
    {
        $expectedNames = ['theme1', 'theme2'];

        // Only one storage access for two calls
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($expectedNames);

        $service = $this->createService(storage: $storage);

        // First call - should hit storage, second - use cache
        $result1 = $service->getActiveThemeNames();
        $result2 = $service->getActiveThemeNames();

        static::assertSame($expectedNames, $result1);
        static::assertSame($expectedNames, $result2);
    }

    public function testDeleteByTechnicalName(): void
    {
        $technicalName = 'test-theme';

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($technicalName);

        // Verify cache is cleared by checking storage is called again after delete
        $storage
            ->expects($this->exactly(2))
            ->method('getByName')
            ->with($technicalName)
            ->willReturn(null);

        $service = $this->createService(storage: $storage);

        // Populate cache
        $service->getRuntimeConfigByName($technicalName);

        // Delete - should call storage and reset cache
        $service->deleteByTechnicalName($technicalName);

        // This call should hit storage again (cache was reset)
        $service->getRuntimeConfigByName($technicalName);
    }

    private function createService(
        ?ThemeFileResolver $themeFileResolver = null,
        ?StorefrontPluginRegistry $pluginRegistry = null,
        ?ThemeMergedConfigBuilder $mergedConfigBuilder = null,
        ?ThemeRuntimeConfigStorage $storage = null,
    ): ThemeRuntimeConfigService {
        return new ThemeRuntimeConfigService(
            $themeFileResolver ?? $this->themeFileResolver,
            $pluginRegistry ?? $this->pluginRegistry,
            $mergedConfigBuilder ?? $this->mergedConfigBuilder,
            $storage ?? $this->storage,
            new NativeClock()
        );
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
