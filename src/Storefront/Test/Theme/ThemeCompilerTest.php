<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Storefront\Theme\Event\ThemeCopyToLiveEvent;
use Shopware\Storefront\Theme\Exception\ThemeFileCopyException;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ThemeCompilerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

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
        /** @var EventDispatcherInterface eventDispatcher */
        $this->eventDispatcher = $this->getContainer()->get(EventDispatcherInterface::class);

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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId);

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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId);

        $expected = <<<PHP_EOL
// ATTENTION! This file is auto generated by the Shopware\Storefront\Theme\ThemeCompiler and should not be edited.

\$sw-color-brand-primary: #008490;
\$sw-color-brand-secondary: #526e7f;
\$sw-asset-theme-url: 'http://localhost';

PHP_EOL;

        static::assertSame($expected, $actual);
    }

    public function testDumpVariablesHasNoConfigFieldsAndReturnsEmptyString(): void
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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId);

        static::assertSame('', $actual);
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

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId);

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

        $event = new ThemeCompilerEnrichScssVariablesEvent($variables, $this->mockSalesChannelId);
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

        $compiler->compileTheme(
            TestDefaults::SALES_CHANNEL,
            'test',
            $config,
            new StorefrontPluginConfigurationCollection()
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
                new StorefrontPluginConfigurationCollection()
            );
        } finally {
            rmdir($testFolder);
        }
    }

    public function testOutputsOnlyExpectedCssWhenUsingFeatureFlagFunction(): void
    {
        if ($_SERVER['FEATURE_ALL']) {
            static::markTestSkipped('Skipped because fixture feature `FEATURE_NEXT_2` should be false.');
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
}
