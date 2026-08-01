<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem as ThemeFilesystem;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\NotFoundPlugin\NotFoundPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerTest extends TestCase
{
    use EnvTestBehaviour;

    private string $mockSalesChannelId;

    /**
     * @var ThemeFileResolver&Stub
     */
    private ThemeFileResolver $themeFileResolver;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    /**
     * @var EventDispatcher&Stub
     */
    private EventDispatcher $eventDispatcher;

    /**
     * @var CacheInvalidator&Stub
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var LoggerInterface&Stub
     */
    private LoggerInterface $logger;

    /**
     * @var ScssPhpCompiler&Stub
     */
    private ScssPhpCompiler $scssPhpCompiler;

    private MD5ThemePathBuilder $pathBuilder;

    private ThemeFilesystemResolver&Stub $themeFilesystemResolver;

    /**
     * @var CopyBatchInputFactory&Stub
     */
    private CopyBatchInputFactory $copyBatchInputFactory;

    protected function setUp(): void
    {
        $this->themeFileResolver = static::createStub(ThemeFileResolver::class);
        $this->eventDispatcher = static::createStub(EventDispatcher::class);
        $this->cacheInvalidator = static::createStub(CacheInvalidator::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->scssPhpCompiler = static::createStub(ScssPhpCompiler::class);
        $this->pathBuilder = new MD5ThemePathBuilder();
        $this->copyBatchInputFactory = static::createStub(CopyBatchInputFactory::class);
        $this->themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';
    }

    public function testThemeCompileExceptionIsThrownWhenFilesAreNotResolved(): void
    {
        $this->themeFileResolver->method('resolveStyleFiles')->willThrowException(new \InvalidArgumentException());
        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testThemeCompileExceptionIsThrownWhenConcatenateFails(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [ThemeFileResolver::STYLE_FILES => FileCollection::createFromArray(['foo'])]
        );

        $this->eventDispatcher->method('dispatch')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testThemeCompileExceptionIsThrownWhenCollectCompiledFilesFails(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [ThemeFileResolver::STYLE_FILES => FileCollection::createFromArray(['foo'])]
        );

        $this->copyBatchInputFactory->method('fromDirectory')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');
        $config->setAssetPaths(['bla']);

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('configForDumpVariables')]
    public function testDumpVariables(array $config, string $expected): void
    {
        $themeConfig = new StorefrontPluginConfiguration('test');
        $themeConfig->setThemeConfig($config);

        $this->getThemeCompiler()->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'themeId',
            $themeConfig,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertSame($expected, $this->tempFilesystem->read('theme-variables.scss'));
        static::assertSame($expected, $this->tempFilesystem->read('theme-variables/themeId.scss'));
    }

    public static function configForDumpVariables(): \Generator
    {
        yield 'finds config fields and returns string with scss variables' => [
            [
                'fields' => [
                    'sw-color-brand-primary' => [
                        'name' => 'sw-color-brand-primary',
                        'type' => 'color',
                        'value' => '#008490',
                    ],
                    'sw-color-brand-secondary' => [
                        'name' => 'sw-color-brand-secondary',
                        'type' => 'color',
                        'value' => '#526e7f',
                    ],
                    'sw-border-color' => [
                        'name' => 'sw-border-color',
                        'type' => 'color',
                        'value' => '#bcc1c7',
                    ],
                    'sw-custom-header' => [
                        'name' => 'sw-custom-header',
                        'type' => 'checkbox',
                        'value' => false,
                    ],
                    'sw-custom-footer' => [
                        'name' => 'sw-custom-header',
                        'type' => 'checkbox',
                        'value' => true,
                    ],
                    'sw-custom-cart' => [
                        'name' => 'sw-custom-header',
                        'type' => 'switch',
                        'value' => false,
                    ],
                    'sw-custom-product-box' => [
                        'name' => 'sw-custom-header',
                        'type' => 'switch',
                        'value' => true,
                    ],
                    'sw-text-field' => [
                        'name' => 'sw-text-field',
                        'type' => 'text',
                        'value' => '2px solid #000',
                    ],
                    'sw-custom-textarea' => [
                        'name' => 'sw-custom-textarea',
                        'type' => 'textarea',
                        'value' => '123',
                    ],
                    'sw-invalid-textarea' => [
                        'name' => 'sw-invalid-textarea',
                        'type' => 'media',
                        'value' => [123],
                    ],
                    'sw-custom-url' => [
                        'name' => 'sw-custom-url',
                        'type' => 'url',
                        'value' => 'https://www.shopware.com',
                    ],
                    'sw-custom-media' => [
                        'name' => 'sw-custom-media',
                        'type' => 'media',
                        'value' => '456',
                    ],
                    'sw-invalid-media' => [
                        'name' => 'sw-invalid-media',
                        'type' => 'media',
                        'value' => [false],
                    ],
                    'sw-invalid-type' => [
                        'name' => 'sw-invalid-type',
                        'value' => [false],
                    ],
                    'sw-multi-test' => [
                        'name' => 'sw-multi-test',
                        'type' => 'text',
                        'value' => [
                            'top',
                            'bottom',
                        ],
                        'custom' => [
                            'componentName' => 'sw-multi-select',
                            'options' => [
                                [
                                    'value' => 'bottom',
                                ],
                                [
                                    'value' => 'top',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-border-color: #bcc1c7;
\$sw-custom-header: 0;
\$sw-custom-footer: 1;
\$sw-custom-cart: 0;
\$sw-custom-product-box: 1;
\$sw-text-field: 2px solid #000;
\$sw-custom-textarea: '123';
\$sw-custom-url: 'https://www.shopware.com';
\$sw-custom-media: '456';
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];

        yield 'ignores fields with scss config property set to false' => [
            [
                'fields' => [
                    'sw-color-brand-primary' => [
                        'name' => 'sw-color-brand-primary',
                        'type' => 'color',
                        'value' => '#008490',
                    ],
                    'sw-color-brand-secondary' => [
                        'name' => 'sw-color-brand-secondary',
                        'type' => 'color',
                        'value' => '#526e7f',
                    ],
                    // Prevent adding field as sass variable
                    'sw-ignore-me' => [
                        'name' => 'sw-border-color',
                        'type' => 'text',
                        'value' => 'Foo bar',
                        'scss' => false,
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];
        yield 'HasNoConfigFieldsAndReturnsOnlyDefaultVariables' => [
            [
                'blocks' => [
                    'themeColors' => [
                        'label' => [
                            'en-GB' => 'Theme colours',
                            'de-DE' => 'Theme-Farben',
                        ],
                    ],
                    'typography' => [
                        'label' => [
                            'en-GB' => 'Typography',
                            'de-DE' => 'Typografie',
                        ],
                    ],
                ],
            ],
            '// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

$theme-id: themeId;
$sw-asset-theme-url: \'http://localhost\';
',
        ];
        yield 'MayHaveZeroValueButNotNull' => [
            [
                'fields' => [
                    'sw-zero-margin' => [
                        'name' => 'sw-zero-margin',
                        'type' => 'text',
                        'value' => 0,
                    ],
                    'sw-null-margin' => [
                        'name' => 'sw-null-margin',
                        'type' => 'text',
                        'value' => null,
                    ],
                    'sw-unset-margin' => [
                        'name' => 'sw-unset-margin',
                        'type' => 'text',
                    ],
                    'sw-empty-margin' => [
                        'name' => 'sw-empty-margin',
                        'type' => 'text',
                        'value' => '',
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-zero-margin: 0;
\$sw-null-margin: null;
\$sw-unset-margin: null;
\$sw-empty-margin: null;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];
    }

    public function testScssVariablesEventAddsNewVariablesToArray(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(static::createStub(SystemConfigService::class));

        $variables = [
            'sw-color-brand-primary' => '#008490',
            'sw-color-brand-secondary' => '#526e7f',
            'sw-border-color' => '#bcc1c7',
        ];

        $event = new ThemeCompilerEnrichScssVariablesEvent($variables, $this->mockSalesChannelId, Context::createDefaultContext());
        $subscriber->onAddVariables($event);

        $actual = $event->getVariables();

        $expected = [
            'sw-color-brand-primary' => '#008490',
            'sw-color-brand-secondary' => '#526e7f',
            'sw-border-color' => '#bcc1c7',
            'mock-variable-black' => '#000000',
            'mock-variable-special' => '\'Special value with quotes\'',
        ];

        static::assertSame($expected, $actual);
    }

    public function testConcatenatedStylesEventPassThru(): void
    {
        $subscriber = new MockThemeCompilerConcatenatedSubscriber();

        $styles = 'body {}';

        $event = new ThemeCompilerConcatenatedStylesEvent($styles, $this->mockSalesChannelId);
        $subscriber->onGetConcatenatedStyles($event);
        $actual = $event->getConcatenatedStyles();

        $expected = $styles . MockThemeCompilerConcatenatedSubscriber::STYLES_CONCAT;

        static::assertSame($expected, $actual);
    }

    public function testCompileWithoutAssets(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (\Throwable $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->has('theme/9a11a759d278b4a55cb5e2c3414733c1'));
    }

    public function testAssetPathWillBeAbsoluteConverted(): void
    {
        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $fs = new StaticFilesystem(['Resources/assets' => 'directory']);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->expects($this->once())
            ->method('getFilesystemForStorefrontConfig')
            ->with($config)
            ->willReturn($fs);

        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $this->filesystem->createDirectory('temp');
        $this->filesystem->write('temp/test.png', '');
        $png = $this->filesystem->readStream('temp/test.png');

        $this->copyBatchInputFactory->method('fromDirectory')->willReturn(
            [
                new CopyBatchInput($png, ['theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png']),
            ]
        );

        $compiler = $this->getThemeCompiler(themeFilesystemResolver: $themeFilesystemResolver);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (\Throwable $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png'));
    }

    public function testExistingFilesAreNotDeletedOnCompileError(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/9a11a759d278b4a55cb5e2c3414733c1');
        $this->filesystem->write('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler(scssPhpCompiler: $scssPhpCompiler);

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

        $wasThrown = false;

        try {
            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'test',
                $config,
                new StorefrontPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (ThemeCompileException) {
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
        static::assertTrue($this->filesystem->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js'));
    }

    public function testNewFilesAreDeletedOnCompileError(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/all.js', '');

        $copyBatchInputFactory = $this->createMock(CopyBatchInputFactory::class);
        $copyBatchInputFactory->expects($this->never())
            ->method('fromDirectory');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willThrowException(new \Exception());

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->method('generateNewPath')->willReturn('new');
        $this->pathBuilder->expects($this->never())->method('saveSeed');

        $compiler = $this->getThemeCompiler(
            copyBatchInputFactory: $copyBatchInputFactory,
            scssPhpCompiler: $scssPhpCompiler,
        );

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $wasThrown = false;

        try {
            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'test',
                $config,
                new StorefrontPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (ThemeCompileException) {
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
        static::assertTrue($this->filesystem->fileExists('theme/current/all.js'));
        static::assertFalse($this->filesystem->fileExists('theme/new/all.js'));
    }

    public function testOldThemeFilesAreDeletedDelayedOnThemeCompileSuccess(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/all.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willReturn('');

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->expects($this->once())
            ->method('generateNewPath')
            ->with(
                TestDefaults::SALES_CHANNEL,
                'test'
            )
            ->willReturn('new');
        $this->pathBuilder->expects($this->once())
            ->method('saveSeed')
            ->with(TestDefaults::SALES_CHANNEL, 'test');

        $compiler = $this->getThemeCompiler(scssPhpCompiler: $scssPhpCompiler);

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->fileExists('theme/current/all.js'));
    }

    public function testCopyScriptFilesToTheme(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $distLocation = __DIR__ . '/fixtures/ThemeAndPlugin/TestTheme/Resources/app/storefront/dist/storefront/js/test-theme';
        $this->filesystem->createDirectory($distLocation);
        $this->filesystem->write($distLocation . '/test-theme.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->setEnvVars([
            'V6_6_0_0' => 1,
        ]);

        $projectDir = __DIR__ . '/fixtures';
        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $compiler = $this->getThemeCompiler(
            themeFilesystemResolver: $themeFilesystemResolver,
            logger: $logger,
            scssPhpCompiler: $scssPhpCompiler,
        );

        $filesystems = [
            'AsyncPlugin' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/AsyncPlugin'),
            'TestTheme' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/TestTheme'),
            'NotFoundPlugin' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/NotFoundPlugin'),
        ];

        $sourceResolver = new StaticSourceResolver($filesystems);

        $themeFilesystemResolver->expects($this->exactly(\count($filesystems)))
            ->method('getFilesystemForStorefrontConfig')
            ->willReturnCallback(static fn (StorefrontPluginConfiguration $config) => $filesystems[$config->getTechnicalName()]);

        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            $sourceResolver,
            new SymfonyFilesystem(),
        );

        $themePluginBundle = new TestTheme();
        $asyncPluginBundle = new AsyncPlugin(true, $projectDir . 'fixtures/ThemeAndPlugin/AsyncPlugin');
        $notFoundPluginBundle = new NotFoundPlugin(
            true,
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin'
        );
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);
        $asyncPlugin = $configurationFactory->createFromBundle($asyncPluginBundle);
        $appWithoutJs = $configurationFactory->createFromApp('ThemeAppWithoutJs', 'ThemeAppWithoutJs');

        $notFoundPlugin = $configurationFactory->createFromBundle($notFoundPluginBundle);
        $scripts = new FileCollection();
        $scripts = $scripts::createFromArray([
            'Resources/app/storefront/src/plugins/lorem-ipsum/plugin.js',
        ]);
        $notFoundPlugin->setScriptFiles($scripts);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($testTheme);
        $configCollection->add($asyncPlugin);
        $configCollection->add($notFoundPlugin);
        $configCollection->add($appWithoutJs);

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'TestTheme',
            $testTheme,
            $configCollection,
            true,
            Context::createDefaultContext()
        );

        $themeBasePath = '/theme/2fb1d60e66e241fe65bcedc271cc2174';
        $asyncMainJsInTheme = $themeBasePath . '/js/async-plugin/async-plugin.js';
        $asyncAnotherJsFileInTheme = $themeBasePath . '/js/async-plugin/custom_plugins_AsyncPlugin_src_Resources_app_storefront_src_plugins_lorem-ipsum_plugin_js.js';
        $themeMainJsInTheme = $themeBasePath . '/js/test-theme/test-theme.js';

        static::assertTrue($this->filesystem->directoryExists($distLocation));
        static::assertTrue($this->filesystem->fileExists($distLocation . '/test-theme.js'));
        static::assertTrue($this->filesystem->fileExists($asyncMainJsInTheme));
        static::assertTrue($this->filesystem->fileExists($asyncAnotherJsFileInTheme));
        static::assertTrue($this->filesystem->fileExists($themeMainJsInTheme));
    }

    public function testKeepConfigurationCollectionWithGetScriptDistFolders(): void
    {
        $compiler = $this->getThemeCompiler();

        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver([]),
            new SymfonyFilesystem(),
        );

        $themePluginBundle = new TestTheme();
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($testTheme);

        $testTheme->setScriptFiles(
            FileCollection::createFromArray([
                'Resources/app/storefront/src/plugins/lorem-ipsum/plugin.js',
                '@Storefront',
            ])
        );

        $currentConfigCollection = clone $configCollection;

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'TestTheme',
            $testTheme,
            $configCollection,
            true,
            Context::createDefaultContext()
        );

        // There should be no side effects on the configuration collection
        static::assertEquals($currentConfigCollection, $configCollection);
    }

    /**
     * @param array<string> $mappings
     */
    #[DataProvider('importPathsProvider')]
    public function testGetResolveImportPathsCallbackReturnsNull(array $mappings, string $originPath): void
    {
        $compiler = $this->getThemeCompiler();
        $closure = $compiler->getResolveImportPathsCallback($mappings);

        static::assertNull($closure($originPath));
    }

    public static function importPathsProvider(): \Generator
    {
        yield 'no mapping' => [
            [],
            'fake_path',
        ];
        yield 'wrong path without extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path',
        ];
        yield 'wrong path with min extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path.min',
        ];
        yield 'wrong path with zip extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path.zip',
        ];
    }

    protected function getThemeCompiler(
        ?CopyBatchInputFactory $copyBatchInputFactory = null,
        ?ThemeFilesystemResolver $themeFilesystemResolver = null,
        ?LoggerInterface $logger = null,
        ?ScssPhpCompiler $scssPhpCompiler = null,
    ): ThemeCompiler {
        return new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            static::createStub(FilesystemOperator::class),
            $copyBatchInputFactory ?? $this->copyBatchInputFactory,
            $this->themeFileResolver,
            true,
            $this->eventDispatcher,
            $themeFilesystemResolver ?? $this->themeFilesystemResolver,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->cacheInvalidator,
            $logger ?? $this->logger,
            $this->pathBuilder,
            $scssPhpCompiler ?? $this->scssPhpCompiler,
            [],
            false
        );
    }
}
