<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\ThemeCompiler
 */
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
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
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

    public function testConcanatedScriptsEventPassThrough(): void
    {
        $subscriber = new MockThemeCompilerConcatenatedSubscriber();

        $scripts = 'console.log(\'foo\');';

        $event = new ThemeCompilerConcatenatedScriptsEvent($scripts, $this->mockSalesChannelId);
        $subscriber->onGetConcatenatedScripts($event);
        $actual = $event->getConcatenatedScripts();

        $expected = $scripts . MockThemeCompilerConcatenatedSubscriber::SCRIPTS_CONCAT;

        static::assertEquals($expected, $actual);
    }

    public function testCompileWithoutAssets(): void
    {
        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);

        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new MemoryFilesystemAdapter());

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
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
            new MD5ThemePathBuilder(),
            __DIR__,
            $this->createMock(ScssPhpCompiler::class),
            new MessageBus(),
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
            new MD5ThemePathBuilder(),
            __DIR__,
            $scssCompiler,
            new MessageBus(),
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

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->createMock(EventDispatcher::class),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            $pathBuilder,
            __DIR__,
            $scssCompiler,
            new MessageBus(),
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
            $pathBuilder,
            __DIR__,
            $scssCompiler,
            $messageBusMock,
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
