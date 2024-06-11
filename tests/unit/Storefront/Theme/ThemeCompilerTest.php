<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\NotFoundPlugin\NotFoundPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerTest extends TestCase
{
    use EnvTestBehaviour;

    private string $mockSalesChannelId;

    /**
     * @var ThemeFileResolver&MockObject
     */
    private ThemeFileResolver $themeFileResolver;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    /**
     * @var EventDispatcher&MockObject
     */
    private EventDispatcher $eventDispatcher;

    /**
     * @var ThemeFileImporter&MockObject
     */
    private ThemeFileImporter $themeFileImporter;

    /**
     * @var CacheInvalidator&MockObject
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var ScssPhpCompiler&MockObject
     */
    private ScssPhpCompiler $scssPhpCompiler;

    private MD5ThemePathBuilder $pathBuilder;

    private MessageBus $messageBus;

    protected function setUp(): void
    {
        $this->themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->themeFileImporter = $this->createMock(ThemeFileImporter::class);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $this->pathBuilder = new MD5ThemePathBuilder();
        $this->messageBus = new MessageBus();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';
    }

    public function testThemeCompileExceptionIsThrownWhenFilesAreNotResolved(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willThrowException(new InvalidArgumentException());
        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');

        static::expectExceptionObject(new ThemeCompileException('faultyTheme'));
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
        $this->themeFileImporter->method('getConcatenableStylePath')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');

        static::expectExceptionObject(new ThemeCompileException('faultyTheme'));
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

        $this->themeFileImporter->method('getCopyBatchInputsForAssets')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setName('faultyTheme');
        $config->setAssetPaths(['bla']);

        static::expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testFormatVariablesArrayConvertsToNonAssociativeArrayWithValidScssSyntax(): void
    {
        $formatVariables = ReflectionHelper::getMethod(ThemeCompiler::class, 'formatVariables');

        $variables = [
            'sw-color-brand-primary' => '#008490',
            'sw-color-brand-secondary' => '#526e7f',
            'sw-border-color' => '#bcc1c7',
        ];

        $actual = $formatVariables->invoke($this->getThemeCompiler(), $variables);

        $expected = [
            '$sw-color-brand-primary: #008490;',
            '$sw-color-brand-secondary: #526e7f;',
            '$sw-border-color: #bcc1c7;',
        ];

        static::assertSame($expected, $actual);
    }

    /**
     * @param array<string> $config
     */
    #[DataProvider('configForDumpVariables')]
    public function testDumpVariables(array $config, string $expected): void
    {
        $dumpVariables = ReflectionHelper::getMethod(ThemeCompiler::class, 'dumpVariables');

        $actual = $dumpVariables->invoke($this->getThemeCompiler(), $config, 'themeId', $this->mockSalesChannelId, Context::createDefaultContext());

        static::assertSame($expected, $actual);
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
\$sw-custom-textarea: '123';
\$sw-custom-media: '456';
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL
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

PHP_EOL
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
                        'name' => 'sw-null-margin',
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
                        'name' => 'sw-unset-margin',
                        'type' => 'text',
                        'value' => '',
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-zero-margin: 0;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL
        ];
    }

    public function testScssVariablesEventAddsNewVariablesToArray(): void
    {
        $subscriber = new MockThemeVariablesSubscriber($this->createMock(SystemConfigService::class));

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

        static::assertEquals($expected, $actual);
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
        static::assertEquals('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

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
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $this->filesystem->createDirectory('temp');
        $this->filesystem->write('temp/test.png', '');
        $png = $this->filesystem->readStream('temp/test.png');

        $this->themeFileImporter->method('getCopyBatchInputsForAssets')->with('assets')->willReturn(
            [
                new CopyBatchInput($png, ['theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png']),
            ]
        );

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertEquals('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

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

        $this->themeFileImporter->expects(static::never())
            ->method('getCopyBatchInputsForAssets');

        $this->scssPhpCompiler->expects(static::once())->method('compileString')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertEquals('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

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

        $this->themeFileImporter->expects(static::never())
            ->method('getCopyBatchInputsForAssets');

        $this->scssPhpCompiler->expects(static::once())->method('compileString')->willThrowException(new \Exception());

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->method('generateNewPath')->willReturn('new');
        $this->pathBuilder->expects(static::never())->method('saveSeed');

        $compiler = $this->getThemeCompiler();

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

        $this->themeFileImporter->expects(static::once())
            ->method('getCopyBatchInputsForAssets');

        $this->scssPhpCompiler->expects(static::once())->method('compileString')->willReturn('');

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->expects(static::once())
            ->method('generateNewPath')
            ->with(
                TestDefaults::SALES_CHANNEL,
                'test'
            )
            ->willReturn('new');
        $this->pathBuilder->expects(static::once())
            ->method('saveSeed')
            ->with(TestDefaults::SALES_CHANNEL, 'test');

        $expectedMessage = new DeleteThemeFilesMessage('current', TestDefaults::SALES_CHANNEL, 'test');
        $expectedStamps = [new DelayStamp(900000)];

        $expectedEnvelop = new Envelope($expectedMessage, $expectedStamps);

        $this->messageBus = $this->createMock(MessageBus::class);
        $this->messageBus->expects(static::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps)
            ->willReturn($expectedEnvelop);

        $compiler = $this->getThemeCompiler(__DIR__, 900);

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

        $distLocation = 'fixtures/ThemeAndPlugin/TestTheme/Resources/app/storefront/dist/storefront/js/test-theme';
        $this->filesystem->createDirectory($distLocation);
        $this->filesystem->write($distLocation . '/test-theme.js', '');

        $this->scssPhpCompiler->expects(static::once())->method('compileString')->willReturn('');

        $this->logger->expects(static::once())->method('error');

        $this->setEnvVars([
            'V6_6_0_0' => 1,
        ]);

        $this->themeFileImporter->method('getRealPath')->willReturnCallback(function ($filePath) {
            return $filePath;
        });

        $projectDir = 'tests/unit/Storefront/Theme/fixtures';
        $compiler = $this->getThemeCompiler($projectDir);

        $configurationFactory = new StorefrontPluginConfigurationFactory(
            $projectDir,
            $this->createMock(KernelPluginLoader::class)
        );
        $themePluginBundle = new TestTheme();
        $asyncPluginBundle = new AsyncPlugin(true, $projectDir . 'fixtures/ThemeAndPlugin/AsyncPlugin');
        $notFoundPluginBundle = new NotFoundPlugin(
            true,
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin'
        );
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);
        $asyncPlugin = $configurationFactory->createFromBundle($asyncPluginBundle);
        $app = $configurationFactory->createFromApp('ThemeApp', 'ThemeApp');

        $appWrongPath = $projectDir . '/tmp/207973030/1_0_0/Resources'; // missing ThemeApp in path
        $app->setBasePath($appWrongPath);
        $appWithoutJs = $configurationFactory->createFromApp('ThemeAppWithoutJs', 'ThemeAppWithoutJs');

        $notFoundPlugin = $configurationFactory->createFromBundle($notFoundPluginBundle);
        $scripts = new FileCollection();
        $scripts = $scripts::createFromArray([
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin/src/Resources/app/storefront/src/plugins/lorem-ipsum/plugin.js',
        ]);
        $notFoundPlugin->setScriptFiles($scripts);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($testTheme);
        $configCollection->add($asyncPlugin);
        $configCollection->add($notFoundPlugin);
        $configCollection->add($app);
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
        $appJsFile = $themeBasePath . '/js/theme-app/theme-app.js';

        static::assertTrue($this->filesystem->directoryExists($distLocation));
        static::assertTrue($this->filesystem->fileExists($distLocation . '/test-theme.js'));
        static::assertTrue($this->filesystem->fileExists($asyncMainJsInTheme));
        static::assertTrue($this->filesystem->fileExists($asyncAnotherJsFileInTheme));
        static::assertTrue($this->filesystem->fileExists($themeMainJsInTheme));
        static::assertTrue($this->filesystem->fileExists($appJsFile));
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

    protected function getThemeCompiler(string $projectDir = __DIR__, int $themeFileDeleteDelay = 0): ThemeCompiler
    {
        return new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            $this->themeFileResolver,
            true,
            $this->eventDispatcher,
            $this->themeFileImporter,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->cacheInvalidator,
            $this->logger,
            $this->pathBuilder,
            $projectDir,
            $this->scssPhpCompiler,
            $this->messageBus,
            $themeFileDeleteDelay,
            false
        );
    }
}
