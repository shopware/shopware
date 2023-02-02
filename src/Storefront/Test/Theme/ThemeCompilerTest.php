<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent as ThemeCompilerEnrichScssVariablesEventDep;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Event\ThemeCopyToLiveEvent;
use Shopware\Storefront\Theme\Exception\ThemeFileCopyException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ThemeCompilerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;

    /**
     * @var ThemeCompiler
     */
    private $themeCompiler;

    /**
     * @var string
     */
    private $mockSalesChannelId;

    /**
     * @var string
     */
    private $mockMediaId;

    private EventDispatcherInterface $eventDispatcher;

    public function setUp(): void
    {
        $themeFileResolver = $this->getContainer()->get(ThemeFileResolver::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        // Avoid filesystem operations
        $mockFilesystem = $this->createMock(FileSystem::class);

        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';

        // Insert demo media
        $this->mockMediaId = '98432def39fc4624b33213a56b8c955d';
        $data = [
            'id' => $this->mockMediaId,
            'fileName' => 'testImage',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
        ];

        $mediaRepository = $this->getContainer()->get('media.repository');
        $mediaRepository->create([$data], Context::createDefaultContext());

        $this->themeCompiler = new ThemeCompiler(
            $mockFilesystem,
            $mockFilesystem,
            $themeFileResolver,
            true,
            $this->eventDispatcher,
            $this->getContainer()->get(ThemeFileImporter::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->getContainer()->get(CacheInvalidator::class),
            new MD5ThemePathBuilder(),
            $this->getContainer()->getParameter('kernel.project_dir')
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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
    }

    public function testDumpVariablesHasNoConfigFieldsAndReturnsOnlyAssetUrl(): void
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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId, Context::createDefaultContext());

        static::assertSame('// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId, Context::createDefaultContext());

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$sw-zero-margin: 0;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
    }

    public function testScssVariablesEventAddsNewVariablesToArray(): void
    {
        $subscriber = new MockThemeVariablesSubscriber($this->getContainer()->get(SystemConfigService::class));

        $variables = [
            'sw-color-brand-primary' => '#008490',
            'sw-color-brand-secondary' => '#526e7f',
            'sw-border-color' => '#bcc1c7',
        ];

        if (Feature::isActive('v6.5.0.0')) {
            $event = new ThemeCompilerEnrichScssVariablesEvent($variables, $this->mockSalesChannelId, Context::createDefaultContext());
            $subscriber->onAddVariables($event);
        } else {
            $event = new ThemeCompilerEnrichScssVariablesEventDep($variables, $this->mockSalesChannelId);
            $subscriber->onAddVariablesDep($event);
        }

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

    public function testCompileDeprecatedVersionWorks(): void
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';

        if (!file_exists($testFolder)) {
            mkdir($testFolder);
        }

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->method('getCopyBatchInputsForAssets')->with($testFolder);

        $fs = new Filesystem(new MemoryAdapter());
        $fs->addPlugin(new CopyBatch());
        $tmpFs = new Filesystem(new MemoryAdapter());
        $tmpFs->addPlugin(new CopyBatch());

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
            $this->getContainer()->getParameter('kernel.project_dir')
        );

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertEquals('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (DecorationPatternException $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        if (Feature::isActive('v6.5.0.0')) {
            static::expectExceptionMessage('Tried to access deprecated functionality: The parameter context in method compileTheme of class Shopware\Storefront\Theme\ThemeCompiler is mandatory.');
        }

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection(),
            true
        );

        rmdir($testFolder);
    }

    public function testAssetPathWillBeAbsoluteConverted(): void
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';

        if (!file_exists($testFolder)) {
            mkdir($testFolder);
        }

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->method('getCopyBatchInputsForAssets')->with($testFolder);

        $fs = new Filesystem(new MemoryAdapter());
        $fs->addPlugin(new CopyBatch());
        $tmpFs = new Filesystem(new MemoryAdapter());
        $tmpFs->addPlugin(new CopyBatch());

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
            $this->getContainer()->getParameter('kernel.project_dir')
        );

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertEquals('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (DecorationPatternException $e) {
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

        rmdir($testFolder);
    }

    /**
     * @dataProvider copyToLiveData
     */
    public function testCopyToLive(bool $success, string $failedPath): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_15381', $this);
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';

        if (!file_exists($testFolder)) {
            mkdir($testFolder);
        }

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->method('getCopyBatchInputsForAssets')->with($testFolder);

        $fs = new Filesystem(new MemoryAdapter());
        $fs->addPlugin(new CopyBatch());
        $tmpFs = new Filesystem(new MemoryAdapter());
        $tmpFs->addPlugin(new CopyBatch());

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->eventDispatcher,
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            new MD5ThemePathBuilder(),
            $this->getContainer()->getParameter('kernel.project_dir')
        );

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $pathBuilder = new MD5ThemePathBuilder();
        $themePrefix = $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'test');

        try {
            $pathBuilder->getDecorated();
        } catch (DecorationPatternException $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        $toLiveFn = function (ThemeCopyToLiveEvent $event) use ($failedPath, $success, $fs, $themePrefix): void {
            $pathPrefix = 'theme' . \DIRECTORY_SEPARATOR;

            if ($success === true) {
                static::assertSame($event->getPath(), $pathPrefix . $themePrefix);
                static::assertSame(
                    $event->getBackupPath(),
                    $pathPrefix . 'backup' . \DIRECTORY_SEPARATOR . $themePrefix
                );
                static::assertSame($event->getTmpPath(), $pathPrefix . 'temp' . \DIRECTORY_SEPARATOR . $themePrefix);
            } elseif ($failedPath === 'temp') {
                $event->setTmpPath('anywhere');
            } elseif ($failedPath === 'backup') {
                $fs->createDir($event->getPath());
                $fs->write($event->getBackupPath(), '');
            }
        };

        if ($success === false && $failedPath === 'temp') {
            $this->expectException(ThemeFileCopyException::class);
            $this->expectExceptionMessage('Unable to move the files of theme "test". Compilation error. Compiled files not found in anywhere.');
        } elseif ($success === false && $failedPath === 'backup') {
            $this->expectException(ThemeFileCopyException::class);
            $this->expectExceptionMessage(
                'Unable to move the files of theme "test". File already exists at path: theme' . \DIRECTORY_SEPARATOR
                . 'backup' . \DIRECTORY_SEPARATOR . $themePrefix
            );
        }

        try {
            $this->addEventListener($this->eventDispatcher, ThemeCopyToLiveEvent::class, $toLiveFn);

            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'test',
                $config,
                new StorefrontPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } finally {
            rmdir($testFolder);
        }
    }

    public function testDBException(): void
    {
        $configService = $this->getConfigurationServiceDbException(
            [
                new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
            ]
        );

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry(
            [
                new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
            ]
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);
        $stderr = fopen('php://stderr', 'wb');

        $subscriber->enrichExtensionVars(new ThemeCompilerEnrichScssVariablesEvent([], TestDefaults::SALES_CHANNEL, Context::createDefaultContext()));
    }

    /**
     * Theme compilation should be able to run without a database connection.
     */
    public function testCompileWithoutDB(): void
    {
        $this->stopTransactionAfter();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://user:no@mysql:3306/test_db']);
        KernelLifecycleManager::bootKernel(false, 'noDB');
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';

        if (!file_exists($testFolder)) {
            mkdir($testFolder);
        }

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $importer = $this->createMock(ThemeFileImporter::class);
        $importer->method('getCopyBatchInputsForAssets')->with($testFolder);

        $fs = new Filesystem(new MemoryAdapter());
        $fs->addPlugin(new CopyBatch());
        $tmpFs = new Filesystem(new MemoryAdapter());
        $tmpFs->addPlugin(new CopyBatch());

        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $resolver,
            true,
            $this->getContainer()->get('event_dispatcher'),
            $importer,
            [],
            $this->createMock(CacheInvalidator::class),
            new MD5ThemePathBuilder(),
            $this->getContainer()->getParameter('kernel.project_dir')
        );

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        try {
            $compiler->compileTheme(
                TestDefaults::SALES_CHANNEL,
                'test',
                $config,
                new StorefrontPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (\Throwable $throwable) {
            static::fail('ThemeCompiler->compile() should be executable without a database connection. But following Excpetion was thrown: ' . $throwable->getMessage());
        } finally {
            $this->resetEnvVars();
            KernelLifecycleManager::bootKernel(true);
            $this->startTransactionBefore();
            rmdir($testFolder);
        }
    }

    public function testOutputsPluginCss(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeCustomCss');
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $compileStyles = $themeCompilerReflection->getMethod('compileStyles');
        $compileStyles->setAccessible(true);

        $testScss = <<<PHP_EOL
.test-selector-plugin {
        background: \$simple-plugin-backgroundcolor;
        color: \$simple-plugin-fontcolor;
        border: \$simple-plugin-bordercolor;
}
.test-selector-app {
        background: \$no-theme-custom-css-backgroundcolor;
        color: \$no-theme-custom-css-fontcolor;
        border: \$no-theme-custom-css-bordercolor;
}

PHP_EOL;

        $expectedCssOutput = <<<PHP_EOL
.test-selector-plugin {
\tbackground: #fff;
\tcolor: #eee;
\tborder: 0;
}

.test-selector-app {
\tbackground: #aaa;
\tcolor: #eee;
\tborder: 0;
}
PHP_EOL;

        $configService = $this->getConfigurationService(
            [
                new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
            ]
        );

        $storefrontPluginRegistry = $this->getStorefrontPluginRegistry(
            [
                new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'),
            ]
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);

        $this->eventDispatcher->addSubscriber($subscriber);

        /** @var SystemConfigService $sysConfService */
        $sysConfService = $this->getContainer()->get(SystemConfigService::class);
        $sysConfService->set('SimplePlugin.config.simplePluginBackgroundcolor', '#fff');
        $sysConfService->set('SwagNoThemeCustomCss.config.noThemeCustomCssBackGroundcolor', '#aaa');

        try {
            $actual = $compileStyles->invoke(
                $this->themeCompiler,
                $testScss,
                new StorefrontPluginConfiguration('test'),
                [],
                '1337'
            );
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }

        static::assertSame(trim($expectedCssOutput), trim($actual));
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
            ['any'],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $backupEvent = clone $event;

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertEquals($backupEvent, $event);
    }

    public function testOutputsOnlyExpectedCssWhenUsingFeatureFlagFunction(): void
    {
        if (EnvironmentHelper::getVariable('FEATURE_ALL')) {
            static::markTestSkipped('Skipped because fixture feature `FEATURE_ALL` should be false.');
        }

        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $compileStyles = $themeCompilerReflection->getMethod('compileStyles');
        $compileStyles->setAccessible(true);

        Feature::registerFeatures([
            'FEATURE_NEXT_1' => ['default' => true],
            'FEATURE_NEXT_2' => ['default' => false],
        ]);

        // Ensure feature flag mixin SCSS file is given
        $featureMixin = file_get_contents(
            __DIR__ . '/../../Resources/app/storefront/src/scss/abstract/functions/feature.scss'
        );

        $testScss = <<<PHP_EOL
.test-selector {
    @if feature('FEATURE_NEXT_1') {
        background: yellow;
    } @else {
        background: blue;
    }
    color: red;
}

@if feature('FEATURE_NEXT_2') {
    .not-here {
        display: none;
        // Should not throw when undefined var is behind inactive flag
        color: \$undefined-variable;
    }
}
PHP_EOL;

        $expectedCssOutput = <<<PHP_EOL
.test-selector {
\tbackground: yellow;
\tcolor: red;
}
PHP_EOL;

        $actual = $compileStyles->invoke(
            $this->themeCompiler,
            $featureMixin . $testScss,
            new StorefrontPluginConfiguration('test'),
            [],
            '1337'
        );

        static::assertSame(trim($expectedCssOutput), trim($actual));
    }

    public function testVendorImportFiles(): void
    {
        $themeCompilerReflection = new \ReflectionClass(ThemeCompiler::class);
        $compileStyles = $themeCompilerReflection->getMethod('compileStyles');
        $compileStyles->setAccessible(true);

        $testScss = <<<PHP_EOL
@import '~vendor/library.min'; // Test import for plain CSS without extension
@import '~vendor/library.min.css'; // Test import for plain CSS with explicit extension (deprecated)
@import '~vendor/another-library'; // Test import of SCSS module
@import '~vendor/another-library.scss'; // Test import of SCSS module with explicit extension
PHP_EOL;

        $expectedCssOutput = <<<PHP_EOL
.plain-css-from-library {
\tcolor: red;
}

.plain-css-from-library {
\tcolor: red;
}

.another-lib {
\tcolor: #0d9c0d;
}

.another-lib {
\tcolor: #0d9c0d;
}
PHP_EOL;

        $actual = $compileStyles->invoke(
            $this->themeCompiler,
            $testScss,
            new StorefrontPluginConfiguration('test'),
            [
                'vendor' => __DIR__ . '/fixtures/ThemeWithScssVendorImports/Storefront/Resources/app/storefront/vendor',
            ],
            '1337'
        );

        static::assertSame(trim($expectedCssOutput), trim($actual));
    }

    public function copyToLiveData(): array
    {
        return [
            [
                true,
                '',
            ],
            [
                false,
                'temp',
            ],
            [
                false,
                'backup',
            ],
        ];
    }

    private function getConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            $plugins,
            new ConfigReader(),
            $this->getContainer()->get(AppLoader::class),
            $this->getContainer()->get('app.repository'),
            $this->getContainer()->get(SystemConfigService::class)
        );
    }

    private function getConfigurationServiceDbException(array $plugins): ConfigurationService
    {
        return new ConfigurationServiceExcepetion(
            $plugins,
            new ConfigReader(),
            $this->getContainer()->get(AppLoader::class),
            $this->getContainer()->get('app.repository'),
            $this->getContainer()->get(SystemConfigService::class)
        );
    }

    private function getStorefrontPluginRegistry(array $plugins): StorefrontPluginRegistryInterface
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($plugins);

        return new StorefrontPluginRegistry(
            $kernel,
            $this->getContainer()->get(StorefrontPluginConfigurationFactory::class),
            $this->getContainer()->get(ActiveAppsLoader::class)
        );
    }
}

/**
 * @internal
 */
class ConfigurationServiceExcepetion extends ConfigurationService
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function checkConfiguration(string $domain, Context $context): bool
    {
        throw \Doctrine\DBAL\Exception::invalidTableName('any');
    }
}
