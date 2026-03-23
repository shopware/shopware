<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeConfigCacheInvalidator;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * @internal
 *
 * Unit tests for ThemeCompiler focusing on:
 * - Public API contracts
 * - Error handling
 * - Dependency coordination
 * - Cache invalidation
 * - File operation coordination
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerTest extends TestCase
{
    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    private ThemeFileResolver&MockObject $themeFileResolver;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private CacheInvalidator&MockObject $cacheInvalidator;

    private LoggerInterface&MockObject $logger;

    private ScssPhpCompiler&MockObject $scssPhpCompiler;

    private MD5ThemePathBuilder $pathBuilder;

    private ThemeFilesystemResolver&MockObject $themeFilesystemResolver;

    private CopyBatchInputFactory&MockObject $copyBatchInputFactory;

    private TwigComponentHelper&MockObject $twigComponentHelper;

    private LocalFilesystem&MockObject $localFilesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $this->pathBuilder = new MD5ThemePathBuilder();
        $this->copyBatchInputFactory = $this->createMock(CopyBatchInputFactory::class);
        $this->themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $this->twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $this->twigComponentHelper->method('getComponents')->willReturn(new TwigComponentCollection());
        $this->localFilesystem = $this->createMock(LocalFilesystem::class);
        $this->localFilesystem->method('exists')->willReturn(false);
    }

    // ===================================
    // Error Handling Tests
    // ===================================

    public function testThrowsExceptionWhenStyleFilesCannotBeResolved(): void
    {
        $this->themeFileResolver
            ->method('resolveStyleFiles')
            ->willThrowException(new \InvalidArgumentException('Cannot resolve files'));

        $config = $this->createThemeConfig('TestTheme');
        $compiler = $this->createThemeCompiler();

        $this->expectExceptionObject(new ThemeCompileException('TestTheme'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    public function testThrowsExceptionWhenConcatenationFails(): void
    {
        $this->setupBasicFileResolution();

        $this->eventDispatcher
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Event dispatch failed'));

        $config = $this->createThemeConfig('TestTheme');
        $compiler = $this->createThemeCompiler();

        $this->expectExceptionObject(new ThemeCompileException('TestTheme'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    public function testThrowsExceptionWhenAssetCollectionFails(): void
    {
        $this->setupBasicFileResolution();

        $this->copyBatchInputFactory
            ->method('fromDirectory')
            ->willThrowException(new \RuntimeException('Cannot copy assets'));

        $config = $this->createThemeConfig('TestTheme', assetPaths: ['assets']);
        $compiler = $this->createThemeCompiler();

        $this->expectExceptionObject(new ThemeCompileException('TestTheme'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true, // withAssets = true
            Context::createDefaultContext()
        );
    }

    public function testThrowsExceptionWhenScssCompilationFails(): void
    {
        $this->setupBasicFileResolution();

        $this->scssPhpCompiler
            ->method('compileString')
            ->willThrowException(new \Exception('SCSS compilation error'));

        $config = $this->createThemeConfig('TestTheme');
        $compiler = $this->createThemeCompiler();

        $this->expectExceptionObject(new ThemeCompileException('TestTheme - Theme-ID: theme-id', 'SCSS compilation error'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    public function testExistingFilesAreNotDeletedOnCompileError(): void
    {
        $styleFiles = FileCollection::createFromArray(['test.scss']);
        $this->themeFileResolver->method('resolveStyleFiles')->willReturn($styleFiles);
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => $styleFiles,
        ]);

        // Create existing files
        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/css/all.css', 'existing content');

        $this->scssPhpCompiler
            ->method('compileString')
            ->willThrowException(new \Exception('Compilation failed'));

        // Mock path builder to return same path (non-seeded scenario)
        $pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('current');
        $pathBuilder->method('generateNewPath')->willReturn('new');
        $pathBuilder->expects($this->never())->method('saveSeed');

        $compiler = $this->createThemeCompiler($pathBuilder);
        $config = $this->createThemeConfig('TestTheme');

        $this->expectExceptionObject(new ThemeCompileException('TestTheme - Theme-ID: theme-id', 'Compilation failed'));

        try {
            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'theme-id',
                $config,
                new StorefrontPluginConfigurationCollection(),
                false,
                Context::createDefaultContext()
            );
        } finally {
            // Verify existing files still exist
            static::assertTrue($this->filesystem->fileExists('theme/current/css/all.css'));
            static::assertSame('existing content', $this->filesystem->read('theme/current/css/all.css'));
        }
    }

    public function testNewDirectoryIsNotCreatedOnCompileError(): void
    {
        $styleFiles = FileCollection::createFromArray(['test.scss']);
        $this->themeFileResolver->method('resolveStyleFiles')->willReturn($styleFiles);
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => $styleFiles,
        ]);

        $this->scssPhpCompiler
            ->method('compileString')
            ->willThrowException(new \Exception('Compilation failed'));

        $pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('current');
        $pathBuilder->method('generateNewPath')->willReturn('new');

        $compiler = $this->createThemeCompiler($pathBuilder);
        $config = $this->createThemeConfig('TestTheme');

        $this->expectExceptionObject(new ThemeCompileException('TestTheme - Theme-ID: theme-id', 'Compilation failed'));

        try {
            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'theme-id',
                $config,
                new StorefrontPluginConfigurationCollection(),
                false,
                Context::createDefaultContext()
            );
        } finally {
            static::assertFalse($this->filesystem->directoryExists('theme/new'));
        }
    }

    // ===================================
    // Cache Invalidation Tests
    // ===================================

    public function testCacheIsInvalidatedAfterSuccessfulCompilation(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('compiled css');

        $themeId = 'test-theme-id';

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with(static::callback(function (array $tags) use ($themeId) {
                return \in_array(
                    ThemeConfigCacheInvalidator::buildCacheTag($themeId),
                    $tags,
                    true
                );
            }));

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    public function testCacheIsNotInvalidatedOnCompilationError(): void
    {
        $this->setupBasicFileResolution();

        $this->scssPhpCompiler
            ->method('compileString')
            ->willThrowException(new \Exception('Compilation failed'));

        $this->cacheInvalidator
            ->expects($this->never())
            ->method('invalidate');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $this->expectExceptionObject(new ThemeCompileException('TestTheme - Theme-ID: theme-id', 'Compilation failed'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    // ===================================
    // Event Dispatcher Tests
    // ===================================

    public function testDispatchesVariableEnrichmentEvent(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variableEvents = array_filter(
            $dispatchedEvents,
            fn ($e) => $e instanceof ThemeCompilerEnrichScssVariablesEvent
        );

        static::assertNotEmpty($variableEvents, 'ThemeCompilerEnrichScssVariablesEvent should be dispatched');
    }

    public function testDispatchesConcatenatedStylesEvent(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $concatenatedEvents = array_filter(
            $dispatchedEvents,
            fn ($e) => $e instanceof ThemeCompilerConcatenatedStylesEvent
        );

        static::assertNotEmpty($concatenatedEvents, 'ThemeCompilerConcatenatedStylesEvent should be dispatched');
    }

    public function testVariableEnrichmentEventCanModifyVariables(): void
    {
        $this->setupBasicFileResolution();

        $capturedScss = '';
        $this->scssPhpCompiler
            ->method('compileString')
            ->willReturnCallback(function ($config, $scss) use (&$capturedScss) {
                $capturedScss = $scss;

                return 'compiled css';
            });

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof ThemeCompilerEnrichScssVariablesEvent) {
                    $event->addVariable('custom-variable', '#ff0000');
                }

                return $event;
            });

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertNotEmpty($capturedScss, 'SCSS should have been captured');
        static::assertStringContainsString('$custom-variable: #ff0000', $capturedScss);
    }

    // ===================================
    // Temp Filesystem Tests
    // ===================================

    public function testWritesVariablesToTempFilesystem(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
        static::assertTrue($this->tempFilesystem->has('theme-variables/theme-id.scss'));
    }

    public function testTempFilesystemContainsThemeIdVariable(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $themeId = 'my-theme-id';

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $content = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$theme-id: my-theme-id', $content);
    }

    // ===================================
    // File Operations Tests
    // ===================================

    public function testCreatesThemeDirectoryStructure(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('compiled css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $expectedPath = 'theme/' . $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        static::assertTrue($this->filesystem->has($expectedPath));
    }

    public function testCompilesWithoutAssets(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $this->copyBatchInputFactory
            ->expects($this->never())
            ->method('fromDirectory');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme', assetPaths: ['assets']);

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false, // withAssets = false
            Context::createDefaultContext()
        );
    }

    public function testCopiesToCorrectAssetPath(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $fs = new StaticFilesystem(['Resources/assets' => 'directory']);

        $this->themeFilesystemResolver
            ->method('getFilesystemForStorefrontConfig')
            ->willReturn($fs);

        $this->filesystem->createDirectory('temp');
        $this->filesystem->write('temp/test.png', 'image content');
        $png = $this->filesystem->readStream('temp/test.png');

        $this->copyBatchInputFactory
            ->expects($this->once())
            ->method('fromDirectory')
            ->with(
                static::stringContains('Resources/assets'),
                static::equalTo('theme/theme-id')
            )
            ->willReturn([
                new CopyBatchInput($png, ['theme/assets/test.png']),
            ]);

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme', assetPaths: ['assets']);

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true, // withAssets = true
            Context::createDefaultContext()
        );
    }

    public function testPathBuilderSeedIsSavedOnSuccess(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('old-path');
        $pathBuilder->method('generateNewPath')->willReturn('new-path');
        $pathBuilder
            ->expects($this->once())
            ->method('saveSeed')
            ->with(TestDefaults::SALES_CHANNEL, 'theme-id');

        $compiler = $this->createThemeCompiler($pathBuilder);
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    public function testPathBuilderSeedIsNotSavedOnError(): void
    {
        $this->setupBasicFileResolution();

        $this->scssPhpCompiler
            ->method('compileString')
            ->willThrowException(new \Exception('Compilation failed'));

        $pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('old-path');
        $pathBuilder->method('generateNewPath')->willReturn('new-path');
        $pathBuilder
            ->expects($this->never())
            ->method('saveSeed');

        $compiler = $this->createThemeCompiler($pathBuilder);
        $config = $this->createThemeConfig('TestTheme');

        $this->expectExceptionObject(new ThemeCompileException('TestTheme - Theme-ID: theme-id', 'Compilation failed'));

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    // ===================================
    // Script File Tests
    // ===================================

    public function testDoesNotCopyStorefrontScriptFiles(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $config = $this->createThemeConfig('TestTheme');
        $pluginConfig = new StorefrontPluginConfiguration('Plugin');
        $scriptFiles = FileCollection::createFromArray([
            '@Storefront', // Should be filtered out
            'plugin.js',
        ]);
        $pluginConfig->setScriptFiles($scriptFiles);

        $collection = new StorefrontPluginConfigurationCollection();
        $collection->add($config);
        $collection->add($pluginConfig);

        // Setup filesystem for the plugin
        $this->themeFilesystemResolver
            ->method('getFilesystemForStorefrontConfig')
            ->willReturn(new StaticFilesystem([]));

        $compiler = $this->createThemeCompiler();

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            $collection,
            false,
            Context::createDefaultContext()
        );

        // Test passes if no exception is thrown
        // The @Storefront reference should be filtered out
        // Verify that the theme directory was created
        static::assertTrue($this->filesystem->directoryExists('theme/'));
    }

    public function testConfigurationCollectionIsNotMutated(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('css');

        $scriptFiles = FileCollection::createFromArray(['@Storefront', 'plugin.js']);

        $config = $this->createThemeConfig('TestTheme');
        $pluginConfig = new StorefrontPluginConfiguration('Plugin');
        $pluginConfig->setScriptFiles($scriptFiles);

        $collection = new StorefrontPluginConfigurationCollection();
        $collection->add($config);
        $collection->add($pluginConfig);

        $originalCollection = clone $collection;

        $this->themeFilesystemResolver
            ->method('getFilesystemForStorefrontConfig')
            ->willReturn(new StaticFilesystem([]));

        $compiler = $this->createThemeCompiler();

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            $collection,
            false,
            Context::createDefaultContext()
        );

        // Verify the collection was not mutated
        static::assertEquals($originalCollection, $collection);
    }

    public function testCopyComponentScriptFilesIncludesComponentsWithScriptPath(): void
    {
        $this->setupBasicFileResolution();

        $component = new TwigComponent(
            'Sw:Button',
            '/some/path/Sw/Button.html.twig',
            'Storefront'
        );

        $this->twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $this->twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        // Recreate the mock so exists() returns true without conflicts from setUp's willReturn(false).
        $this->localFilesystem = $this->createMock(LocalFilesystem::class);
        $this->localFilesystem->method('exists')->willReturn(true);

        // Replace the filesystem adapter with one that also implements WriteBatchInterface.
        // CopyBatch::copy detects this and calls writeBatch() instead of fopen() on the
        // source path, so no real file is needed on disk.
        $this->filesystem = new Filesystem(
            new class extends InMemoryFilesystemAdapter implements WriteBatchInterface {
                public function writeBatch(CopyBatchInput ...$files): void
                {
                    foreach ($files as $file) {
                        foreach ($file->getTargetFiles() as $target) {
                            $this->write($target, '', new Config());
                        }
                    }
                }
            }
        );

        $compiler = $this->createThemeCompiler();

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $this->createThemeConfig('TestTheme'),
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $themePrefix = $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        $expectedPath = 'theme/' . $themePrefix . '/js/components/Sw/Button.js';

        static::assertTrue($this->filesystem->has($expectedPath));
    }

    public function testCopyComponentScriptFilesSkipsComponentsWithNoScriptFile(): void
    {
        $this->setupBasicFileResolution();

        $component = new TwigComponent(
            'Sw:Badge',
            '/some/path/Sw/Badge.html.twig',
            'Storefront'
        );

        $this->twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        // localFilesystem defaults to exists() = false in setUp

        $compiler = $this->createThemeCompiler();

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $this->createThemeConfig('TestTheme'),
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $themePrefix = $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        $componentDir = 'theme/' . $themePrefix . '/js/components/';
        static::assertFalse($this->filesystem->has($componentDir));
    }

    // ===================================
    // Deployment Safety Tests
    // ===================================

    public function testOldThemeFilesRemainAfterSuccessfulCompilation(): void
    {
        $styleFiles = FileCollection::createFromArray(['test.scss']);
        $this->themeFileResolver->method('resolveStyleFiles')->willReturn($styleFiles);
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => $styleFiles,
        ]);

        // Create existing files in the OLD theme directory
        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/css/all.css', 'old content');
        $this->filesystem->write('theme/current/js/all.js', 'old script');

        $this->scssPhpCompiler->method('compileString')->willReturn('new compiled css');

        // Mock path builder to return different paths (simulating seeded path approach)
        $pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('current');
        $pathBuilder
            ->expects($this->once())
            ->method('generateNewPath')
            ->with(TestDefaults::SALES_CHANNEL, 'theme-id')
            ->willReturn('new');
        $pathBuilder
            ->expects($this->once())
            ->method('saveSeed')
            ->with(TestDefaults::SALES_CHANNEL, 'theme-id');

        $compiler = $this->createThemeCompiler($pathBuilder);
        $config = $this->createThemeConfig('TestTheme', assetPaths: ['assets']);

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        // Verify old files still exist after successful compilation (delayed deletion pattern)
        static::assertTrue($this->filesystem->fileExists('theme/current/css/all.css'));
        static::assertTrue($this->filesystem->fileExists('theme/current/js/all.js'));
        static::assertSame('old content', $this->filesystem->read('theme/current/css/all.css'));

        // Verify new theme directory was created
        static::assertTrue($this->filesystem->directoryExists('theme/new'));
    }

    // ===================================
    // Smoke Tests
    // ===================================

    public function testCompileSucceedsWithBasicConfiguration(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('compiled css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        // This should not throw any exceptions
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // Verify theme directory was created
        $expectedPath = 'theme/' . $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        static::assertTrue($this->filesystem->has($expectedPath));
    }

    public function testCompileSucceedsWithAssetsEnabled(): void
    {
        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('compiled css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme', assetPaths: ['assets']);

        // This should not throw any exceptions
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        // Verify compilation completed
        $expectedPath = 'theme/' . $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        static::assertTrue($this->filesystem->has($expectedPath));
    }

    public function testCompileCallsScssCompilerAtLeastOnce(): void
    {
        $this->setupBasicFileResolution();

        $this->scssPhpCompiler
            ->expects($this->atLeastOnce())
            ->method('compileString')
            ->willReturn('compiled css');

        $compiler = $this->createThemeCompiler();
        $config = $this->createThemeConfig('TestTheme');

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );
    }

    // ===================================
    // Import Path Resolution Tests
    // ===================================

    /**
     * @param array<string, string> $mappings
     */
    #[DataProvider('importPathProvider')]
    public function testResolveImportPathCallback(
        array $mappings,
        string $originalPath,
        ?string $expectedResult
    ): void {
        $compiler = $this->createThemeCompiler();
        $callback = $compiler->getResolveImportPathsCallback($mappings);

        $result = $callback($originalPath);

        static::assertSame($expectedResult, $result);
    }

    public static function importPathProvider(): \Generator
    {
        yield 'no mapping returns null' => [
            'mappings' => [],
            'originalPath' => '~vendor/library',
            'expectedResult' => null,
        ];

        yield 'wrong path without extension returns null' => [
            'mappings' => ['vendor' => '/path/to/vendor'],
            'originalPath' => '~other/library',
            'expectedResult' => null,
        ];

        yield 'path with unsupported extension returns null' => [
            'mappings' => ['vendor' => '/path/to/vendor'],
            'originalPath' => '~vendor/library.zip',
            'expectedResult' => null,
        ];
    }

    public function testCopyComponentScriptFilesIncludesComponentWithExistingScript(): void
    {
        // php://temp is a PHP stream wrapper: fopen() succeeds without touching the real filesystem
        $component = new class('Sw:Button', '/any/path.html.twig', 'Storefront') extends TwigComponent {
            public function getScriptPath(): string
            {
                return 'php://temp';
            }
        };

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')->willReturn(new TwigComponentCollection([$component]));

        $this->setupBasicFileResolution();
        $this->scssPhpCompiler->method('compileString')->willReturn('compiled css');

        $localFilesystem = $this->createMock(LocalFilesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $compiler = new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            $this->copyBatchInputFactory,
            $this->themeFileResolver,
            $twigComponentHelper,
            true,
            $this->eventDispatcher,
            $this->themeFilesystemResolver,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->cacheInvalidator,
            $this->logger,
            $this->pathBuilder,
            $this->scssPhpCompiler,
            [],
            false,
            Visibility::PUBLIC,
            $localFilesystem,
        );

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'theme-id',
            $this->createThemeConfig('TestTheme'),
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $themePrefix = $this->pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-id');
        $expectedPath = 'theme/' . $themePrefix . '/js/components/Sw/Button.js';
        static::assertTrue($this->filesystem->fileExists($expectedPath));
    }

    // ===================================
    // Helper Methods
    // ===================================

    private function createThemeCompiler(?MD5ThemePathBuilder $pathBuilder = null): ThemeCompiler
    {
        return new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            $this->copyBatchInputFactory,
            $this->themeFileResolver,
            $this->twigComponentHelper,
            true, // debug
            $this->eventDispatcher,
            $this->themeFilesystemResolver,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->cacheInvalidator,
            $this->logger,
            $pathBuilder ?? $this->pathBuilder,
            $this->scssPhpCompiler,
            [], // customAllowedRegex
            false, // validate
            Visibility::PUBLIC,
            $this->localFilesystem,
        );
    }

    /**
     * @param array<string, mixed> $themeConfig
     * @param array<int, string> $assetPaths
     */
    private function createThemeConfig(
        string $name,
        array $themeConfig = [],
        array $assetPaths = []
    ): StorefrontPluginConfiguration {
        $config = new StorefrontPluginConfiguration($name);
        $config->setName($name);

        if ($themeConfig !== []) {
            $config->setThemeConfig($themeConfig);
        }

        if ($assetPaths !== []) {
            $config->setAssetPaths($assetPaths);
        }

        return $config;
    }

    private function setupBasicFileResolution(): void
    {
        $this->themeFileResolver
            ->method('resolveStyleFiles')
            ->willReturn(new FileCollection());

        $this->themeFileResolver
            ->method('resolveFiles')
            ->willReturn([
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection(),
            ]);
    }
}
