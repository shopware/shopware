<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeCompilerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use EnvTestBehaviour;
    use KernelTestBehaviour;

    private ThemeCompiler $themeCompiler;

    private string $mockSalesChannelId;

    private EventDispatcherInterface $eventDispatcher;

    private Filesystem $themeFilesystem;

    private MD5ThemePathBuilder $themePathBuilder;

    protected function setUp(): void
    {
        $themeFileResolver = static::getContainer()->get(ThemeFileResolver::class);
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        // Avoid real filesystem operations
        $this->themeFilesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';
        $this->themePathBuilder = new MD5ThemePathBuilder();

        $this->themeCompiler = new ThemeCompiler(
            $this->themeFilesystem,
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            new CopyBatchInputFactory(),
            $themeFileResolver,
            true,
            $this->eventDispatcher,
            static::getContainer()->get(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            static::getContainer()->get(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            $this->themePathBuilder,
            static::getContainer()->get(ScssPhpCompiler::class),
        );
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SourceResolver::class)->reset();
        static::getContainer()->get(ActiveAppsLoader::class)->reset();
    }

    public function testScssVariablesEventAddsNewVariablesToArray(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(static::getContainer()->get(SystemConfigService::class));

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

        static::assertSame($expected, $actual);
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

        $event = new ThemeCompilerEnrichScssVariablesEvent([], TestDefaults::SALES_CHANNEL, Context::createDefaultContext());

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $storefrontPluginRegistry);
        $exception = null;
        try {
            $subscriber->enrichExtensionVars($event);
        } catch (\Throwable $throwable) {
            $exception = $throwable->getMessage();
        }
        // No variables should be added when a DB exception occurs
        static::assertNull($exception, 'No exception should be thrown, found: ' . $exception);
        static::assertEmpty($event->getVariables());
    }

    /**
     * Theme compilation should be able to run without a database connection.
     */
    public function testCompileWithoutDB(): void
    {
        $this->stopTransactionAfter();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://user:no@mysql:3306/test_db']);
        KernelLifecycleManager::bootKernel(false, 'noDB');
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';

        if (!\is_dir($testFolder)) {
            mkdir($testFolder);
        }

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([ThemeFileResolver::SCRIPT_FILES => new FileCollection(), ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $config = new StorefrontPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $tmpFs = new Filesystem(new InMemoryFilesystemAdapter());
        $assetFs = new Filesystem(new InMemoryFilesystemAdapter());
        $compiler = new ThemeCompiler(
            $fs,
            $tmpFs,
            $assetFs,
            new CopyBatchInputFactory(),
            $resolver,
            true,
            static::getContainer()->get('event_dispatcher'),
            $this->createMock(ThemeFilesystemResolver::class),
            [],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            $this->themePathBuilder,
            static::getContainer()->get(ScssPhpCompiler::class),
        );

        $exception = null;
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
            $exception = $throwable->getMessage();
        }

        // Clean up, no matter what
        $this->resetEnvVars();
        KernelLifecycleManager::bootKernel();
        $this->startTransactionBefore();
        rmdir($testFolder);

        static::assertNull($exception, 'ThemeCompiler->compile() should be executable without a database connection. But following Exception was thrown: ' . $exception);
    }

    public function testOutputsPluginCss(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeCustomCss');

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

        /**
         * The border property is omitted because it has a nullish value.
         * It has no default value and is not set like the background color down in the test.
         * The behaviour of the ThemeCompiler will still ad variables with a null value,
         * but SCSS omits property definitions if they reference a variable with null value.
         */
        $expectedCssOutputNoAutoPrefix = <<<PHP_EOL
.test-selector-plugin {
  background: #fff;
  color: #eee;
}
.test-selector-app {
  background: #aaa;
  color: #eee;
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

        $sysConfService = static::getContainer()->get(SystemConfigService::class);
        $sysConfService->set('SimplePlugin.config.simplePluginBackgroundcolor', '#fff');
        $sysConfService->set('SwagNoThemeCustomCss.config.noThemeCustomCssBackGroundcolor', '#aaa');

        try {
            $actual = $this->compileThemeAndGetCss($testScss, new StorefrontPluginConfiguration('test'));
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }

        static::assertSame($expectedCssOutputNoAutoPrefix, trim($actual));
    }

    public function testOutputsOnlyExpectedCssWhenUsingFeatureFlagFunction(): void
    {
        if (EnvironmentHelper::getVariable('FEATURE_ALL')) {
            static::markTestSkipped('Skipped because fixture feature `FEATURE_ALL` should be false.');
        }

        Feature::registerFeatures([
            'FEATURE_NEXT_1' => ['default' => true],
            'FEATURE_NEXT_2' => ['default' => false],
            'V6_5_0_0' => ['default' => false],
        ]);

        // Ensure feature flag mixin SCSS file is given
        $featureMixin = file_get_contents(
            __DIR__ . '/../../../../src/Storefront/Resources/app/storefront/src/scss/abstract/functions/feature.scss'
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
/*
Helper function to check for active feature flags.
==================================================
The `\$sw-features` variable contains a SCSS map of the current feature config.
The variable is injected automatically via ThemeCompiler.php and webpack.config.js.

@sw-package fundamentals@framework

Example:
@if feature('FEATURE_NEXT_1234') {
    // ...
}
*/
.test-selector {
  background: yellow;
  color: red;
}
PHP_EOL;

        $actual = $this->compileThemeAndGetCss($featureMixin . $testScss, new StorefrontPluginConfiguration('test'));

        static::assertSame(trim($expectedCssOutput), trim($actual));
    }

    public function testVendorImportFiles(): void
    {
        $testScss = <<<PHP_EOL
@import '~vendor/library.min'; // Test import for plain CSS without extension
@import '~vendor/library.min.css'; // Test import for plain CSS with explicit extension (deprecated)
@import '~vendor/another-library'; // Test import of SCSS module
@import '~vendor/another-library.scss'; // Test import of SCSS module with explicit extension
PHP_EOL;

        $expectedCssOutput = <<<PHP_EOL
.plain-css-from-library {
  color: red;
}
.plain-css-from-library {
  color: red;
}
.another-lib {
  color: #0d9c0d;
}
.another-lib {
  color: #0d9c0d;
}
PHP_EOL;

        $vendorDir = __DIR__ . '/fixtures/ThemeWithScssVendorImports/Storefront/Resources/app/storefront/vendor';

        // The resolve mapping for `~vendor` imports is taken from the resolved style files of the
        // theme. The style file itself only exists to carry that mapping: the `@import` lines it
        // contributes are discarded again when the injected event replaces the concatenated SCSS,
        // so only the SCSS under test resolves through it. Resolving a style file requires a theme
        // name the filesystem resolver knows; the base theme name maps to the Storefront bundle,
        // every other name would need an installed app.
        $config = new StorefrontPluginConfiguration(StorefrontPluginRegistry::BASE_THEME_NAME);
        $config->setStyleFiles(new FileCollection([
            new File($vendorDir . '/another-library.scss', ['vendor' => $vendorDir]),
        ]));

        $actual = $this->compileThemeAndGetCss($testScss, $config);

        static::assertSame(trim($expectedCssOutput), trim($actual));
    }

    /**
     * Runs a full theme compilation with the given SCSS - injected via the public
     * ThemeCompilerConcatenatedStylesEvent extension point - and returns the compiled CSS.
     */
    private function compileThemeAndGetCss(string $scss, StorefrontPluginConfiguration $config): string
    {
        $listener = static function (ThemeCompilerConcatenatedStylesEvent $event) use ($scss): void {
            $event->setConcatenatedStyles($scss);
        };
        $this->eventDispatcher->addListener(ThemeCompilerConcatenatedStylesEvent::class, $listener);

        try {
            $this->themeCompiler->compileTheme(
                $this->mockSalesChannelId,
                'themeId',
                $config,
                new StorefrontPluginConfigurationCollection(),
                false,
                Context::createDefaultContext()
            );
        } finally {
            $this->eventDispatcher->removeListener(ThemeCompilerConcatenatedStylesEvent::class, $listener);
        }

        $themePrefix = $this->themePathBuilder->assemblePath($this->mockSalesChannelId, 'themeId');

        return $this->themeFilesystem->read('theme/' . $themePrefix . '/css/all.css');
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            $plugins,
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getConfigurationServiceDbException(array $plugins): ConfigurationService
    {
        return new ConfigurationServiceException(
            $plugins,
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getStorefrontPluginRegistry(array $plugins): StorefrontPluginRegistry
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel
            ->method('getBundles')
            ->willReturn($plugins);

        return new StorefrontPluginRegistry(
            $kernel,
            static::getContainer()->get(StorefrontPluginConfigurationFactory::class),
            static::getContainer()->get(ActiveAppsLoader::class)
        );
    }
}

/**
 * @internal
 */
class ConfigurationServiceException extends ConfigurationService
{
    /**
     * @throws Exception
     */
    public function checkConfiguration(string $domain, Context $context): bool
    {
        throw new InvalidPlatformVersion('any');
    }
}
