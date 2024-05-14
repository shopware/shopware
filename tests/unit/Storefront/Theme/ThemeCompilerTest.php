<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeScripts;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\NotFoundPlugin\NotFoundPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerTest extends TestCase
{
    use EnvTestBehaviour;

    private ThemeCompiler $themeCompiler;

    private string $mockSalesChannelId;

    protected function setUp(): void
    {
        $mockThemeFileResolver = $this->createMock(ThemeFileResolver::class);
        $eventDispatcher = $this->createMock(EventDispatcher::class);

        // Avoid filesystem operations
        $mockFilesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';

        $this->themeCompiler = new ThemeCompiler(
            $mockFilesystem,
            $mockFilesystem,
            $mockThemeFileResolver,
            true,
            $eventDispatcher,
            $this->createMock(ThemeFileImporter::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
            new StaticSystemConfigService(),
            0,
            false
        );
    }

    public function testVariablesArrayConvertsToNonAssociativeArrayWithValidScssSyntax(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $formatVariables = $themeCompilerReflection->getMethod('formatVariables');
        $formatVariables->setAccessible(true);

        $variables = [
            'sw-color-brand-primary' => '#008490',
            'sw-color-brand-secondary' => '#526e7f',
            'sw-border-color' => '#bcc1c7',
        ];

        $actual = $formatVariables->invoke($this->themeCompiler, $variables);

        $expected = [
            '$sw-color-brand-primary: #008490;',
            '$sw-color-brand-secondary: #526e7f;',
            '$sw-border-color: #bcc1c7;',
        ];

        static::assertSame($expected, $actual);
    }

    public function testDumpVariablesFindsConfigFieldsAndReturnsStringWithScssVariables(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $dumpVariables = $themeCompilerReflection->getMethod('dumpVariables');
        $dumpVariables->setAccessible(true);

        $mockConfig = [
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
        ];

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, 'themeId', $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-border-color: #bcc1c7;
\$sw-custom-header: 0;
\$sw-custom-footer: 1;
\$sw-custom-cart: 0;
\$sw-custom-product-box: 1;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
    }

    public function testDumpVariablesIgnoresFieldsWithScssConfigPropertySetToFalse(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $dumpVariables = $themeCompilerReflection->getMethod('dumpVariables');
        $dumpVariables->setAccessible(true);

        $mockConfig = [
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
        ];

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, 'themeId', $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
    }

    public function testDumpVariablesHasNoConfigFieldsAndReturnsOnlyDefaultVariables(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $dumpVariables = $themeCompilerReflection->getMethod('dumpVariables');
        $dumpVariables->setAccessible(true);

        // Config without `fields`
        $mockConfig = [
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
        ];

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, 'themeId', $this->mockSalesChannelId, Context::createDefaultContext());

        static::assertSame('// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

$theme-id: themeId;
$sw-asset-theme-url: \'http://localhost\';
', $actual);
    }

    public function testScssVariablesMayHaveZeroValueButNotNull(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $dumpVariables = $themeCompilerReflection->getMethod('dumpVariables');
        $dumpVariables->setAccessible(true);

        $mockConfig = [
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
        ];

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, 'themeId', $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$sw-zero-margin: 0;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
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

    public function testConcanatedStylesEventPassThru(): void
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
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection([new File('js/storefront/storefront.js', [], 'storefront')]), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $systemConfig = new StaticSystemConfigService();

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
            $systemConfig,
            0,
            false
        );

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

        static::assertEquals(['js/storefront/storefront.js'], $systemConfig->get(ThemeScripts::SCRIPT_FILES_CONFIG_KEY . '.9a11a759d278b4a55cb5e2c3414733c1'));

        static::assertTrue($fs->has('theme/9a11a759d278b4a55cb5e2c3414733c1'));
    }

    public function testAssetPathWillBeAbsoluteConverted(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->createDirectory('temp');
        $fs->write('temp/test.png', '');
        $png = $fs->readStream('temp/test.png');

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->method('getCopyBatchInputsForAssets')->with('assets')->willReturn(
            [
                new CopyBatchInput($png, ['theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png']),
            ]
        );

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
            new StaticSystemConfigService(),
            0,
            false
        );

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

        static::assertTrue($fs->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png'));
    }

    public function testExistingFilesAreNotDeletedOnCompileError(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->createDirectory('theme/9a11a759d278b4a55cb5e2c3414733c1');
        $fs->write('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js', '');

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->expects(static::never())
            ->method('getCopyBatchInputsForAssets');

        $scssCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssCompiler->expects(static::once())->method('compileString')->willThrowException(new \Exception());

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            __DIR__,
            $scssCompiler,
            new MessageBus(),
            new StaticSystemConfigService(),
            0,
            false
        );

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
        static::assertTrue($fs->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js'));
    }

    public function testNewFilesAreDeletedOnCompileError(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->createDirectory('theme/current');
        $fs->write('theme/current/all.js', '');

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->expects(static::never())
            ->method('getCopyBatchInputsForAssets');

        $scssCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssCompiler->expects(static::once())->method('compileString')->willThrowException(new \Exception());

        $pathBuilder = $this->createMock(AbstractThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('current');
        $pathBuilder->method('generateNewPath')->willReturn('new');
        $pathBuilder->expects(static::never())->method('saveSeed');

        $systemConfigMock = $this->createMock(SystemConfigService::class);
        $systemConfigMock->expects(static::never())->method('delete');

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            $pathBuilder,
            __DIR__,
            $scssCompiler,
            new MessageBus(),
            $systemConfigMock,
            0,
            false
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
        static::assertTrue($fs->fileExists('theme/current/all.js'));
        static::assertFalse($fs->fileExists('theme/new/all.js'));
    }

    public function testOldThemeFilesAreDeletedDelayedOnThemeCompileSuccess(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->createDirectory('theme/current');
        $fs->write('theme/current/all.js', '');

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->expects(static::once())
            ->method('getCopyBatchInputsForAssets');

        $scssCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssCompiler->expects(static::once())->method('compileString')->willReturn('');

        $pathBuilder = $this->createMock(AbstractThemePathBuilder::class);
        $pathBuilder->method('assemblePath')->willReturn('current');
        $pathBuilder->expects(static::once())
            ->method('generateNewPath')
            ->with(
                TestDefaults::SALES_CHANNEL,
                'test'
            )
            ->willReturn('new');
        $pathBuilder->expects(static::once())
            ->method('saveSeed')
            ->with(TestDefaults::SALES_CHANNEL, 'test');

        $expectedMessage = new DeleteThemeFilesMessage('current', TestDefaults::SALES_CHANNEL, 'test');
        $expectedStamps = [new DelayStamp(900000)];

        $expectedEnvelop = new Envelope($expectedMessage, $expectedStamps);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(static::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps)
            ->willReturn($expectedEnvelop);

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            $pathBuilder,
            __DIR__,
            $scssCompiler,
            $messageBusMock,
            new StaticSystemConfigService(),
            900,
            false
        );

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

        static::assertTrue($fs->fileExists('theme/current/all.js'));
    }

    /**
     * Write a unit test for copyScriptFilesToTheme function.
     */
    public function testCopyScriptFilesToTheme(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $distLocation = 'fixtures/ThemeAndPlugin/TestTheme/Resources/app/storefront/dist/storefront/js/test-theme';
        $fs->createDirectory($distLocation);
        $fs->write($distLocation . '/test-theme.js', '');

        $scssCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssCompiler->expects(static::once())->method('compileString')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $pathBuilder = new MD5ThemePathBuilder();

        $this->setEnvVars([
            'V6_6_0_0' => 1,
        ]);

        $themeFileImporterMock = $this->createMock(ThemeFileImporter::class);
        $themeFileImporterMock->method('getRealPath')->willReturnCallback(function ($filePath) {
            return $filePath;
        });

        $projectDir = 'tests/unit/Storefront/Theme/fixtures';
        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $themeFileImporterMock,
            [],
            $this->createMock(CacheInvalidator::class),
            $logger,
            $pathBuilder,
            $projectDir,
            $scssCompiler,
            new MessageBus(),
            new StaticSystemConfigService(),
            0,
            false
        );

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

        static::assertTrue($fs->directoryExists($distLocation));
        static::assertTrue($fs->fileExists($distLocation . '/test-theme.js'));
        static::assertTrue($fs->fileExists($asyncMainJsInTheme));
        static::assertTrue($fs->fileExists($asyncAnotherJsFileInTheme));
        static::assertTrue($fs->fileExists($themeMainJsInTheme));
        static::assertTrue($fs->fileExists($appJsFile));
    }

    /**
     * EnrichScssVarSubscriber doesn't throw an exception if we have corrupt element values.
     * This can happen on updates from older version when the values in the administration where not checked before save
     */
    public function testOutputsPluginCssCorrupt(): void
    {
        $configService = $this->createMock(ConfigurationService::class);
        $configService->method('getResolvedConfiguration')->willReturn([
            'card' => [
                'elements' => [
                    new \DateTime(),
                ],
            ],
        ]);

        $storefrontPluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            ['bla' => 'any'],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $backupEvent = clone $event;

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertEquals($backupEvent, $event);
    }
}
