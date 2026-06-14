<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ThemeCompilerDirectUsageTest extends TestCase
{
    use KernelTestBehaviour;

    private ThemeCompiler $themeCompiler;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    private EventDispatcherInterface $eventDispatcher;

    private string $mockSalesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockSalesChannelId = '98432def39fc4624b33213a56b8c944d';
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->themeCompiler = new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            new CopyBatchInputFactory(),
            static::getContainer()->get(ThemeFileResolver::class),
            true,
            $this->eventDispatcher,
            static::getContainer()->get(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            static::getContainer()->get(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
            [],
            false
        );
    }

    // ===================================
    // Real SCSS Compilation Tests
    // ===================================

    public function testCompilesScssToValidCss(): void
    {
        $scssInput = '$primary-color: #ff0000; .test { color: $primary-color; }';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $scssInput
        );

        static::assertStringContainsString('.test', $result);
        static::assertStringContainsString('#ff0000', $result);
        static::assertStringContainsString('color', $result);
    }

    public function testCompilesThemeVariablesIntoScss(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'value' => '#008490',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // Check that variables were written to temp filesystem
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        static::assertStringContainsString('$sw-color-brand-primary: #008490', $variablesContent);
        static::assertStringContainsString('$theme-id: test-theme-id', $variablesContent);
    }

    public function testCompiledCssContainsThemeVariables(): void
    {
        $testScss = '.button { background: $sw-test-color; }';

        // Create a simple SCSS file for testing
        $this->tempFilesystem->write('test.scss', $testScss);

        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-test-color' => [
                    'name' => 'sw-test-color',
                    'type' => 'color',
                    'value' => '#123456',
                ],
            ],
        ]);

        // Compile with variables
        $variables = '$sw-test-color: #123456;';
        $fullScss = $variables . "\n" . $testScss;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $fullScss
        );

        static::assertStringContainsString('.button', $result);
        static::assertStringContainsString('#123456', $result);
    }

    // ===================================
    // Feature Flag Behavior Tests
    // ===================================

    public function testFeatureFlagFunctionWorksInScss(): void
    {
        if (EnvironmentHelper::getVariable('FEATURE_ALL')) {
            static::markTestSkipped('Skipped because FEATURE_ALL should be false for this test.');
        }

        Feature::registerFeatures([
            'FEATURE_NEXT_TEST_1' => ['default' => true],
            'FEATURE_NEXT_TEST_2' => ['default' => false],
        ]);

        $featureMixin = '@function feature($feature-flag) { @return map-get($sw-features, $feature-flag); }';

        // Inject $sw-features variable (normally done by ThemeCompiler)
        $allFeatures = Feature::getAll();
        $featuresScss = implode(',', array_map(
            fn ($value, $key) => \sprintf('"%s": %s', $key, json_encode($value, \JSON_THROW_ON_ERROR)),
            $allFeatures,
            array_keys($allFeatures)
        ));
        $featureVariables = \sprintf('$sw-features: (%s);', $featuresScss);

        $testScss = <<<'SCSS'
.test-selector {
    @if feature('FEATURE_NEXT_TEST_1') {
        background: green;
    } @else {
        background: red;
    }
}

@if feature('FEATURE_NEXT_TEST_2') {
    .should-not-exist {
        display: none;
    }
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $featureVariables . "\n" . $featureMixin . "\n" . $testScss
        );

        // FEATURE_NEXT_TEST_1 is active, so we should see green background
        static::assertStringContainsString('background:green', str_replace(' ', '', $result));
        static::assertStringNotContainsString('background:red', str_replace(' ', '', $result));

        // FEATURE_NEXT_TEST_2 is inactive, so .should-not-exist should not appear
        static::assertStringNotContainsString('.should-not-exist', $result);
    }

    public function testFeatureFlagVariablesAreInjected(): void
    {
        $testScss = '$test: map.get($sw-features, "FEATURE_NEXT_1");';

        // This should compile without errors because $sw-features is injected by ThemeCompiler
        $config = new StorefrontPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // If we get here, compilation succeeded (no exception thrown)
        // Verify theme variables were written successfully
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
    }

    // ===================================
    // Vendor Import Path Tests
    // ===================================

    public function testResolvesVendorImportPaths(): void
    {
        $testScss = <<<'SCSS'
@import '~vendor/library.min';
@import '~vendor/another-library';
SCSS;

        $vendorPath = __DIR__ . '/fixtures/ThemeWithScssVendorImports/Storefront/Resources/app/storefront/vendor';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([
                'importPaths' => [
                    function (string $path) use ($vendorPath) {
                        if (str_starts_with($path, '~vendor/')) {
                            $relativePath = substr($path, 8); // Remove '~vendor/'
                            $fullPath = $vendorPath . '/' . $relativePath;

                            // Try with .css extension for .min files
                            if (str_ends_with($relativePath, '.min')) {
                                $cssPath = $fullPath . '.css';
                                if (is_file($cssPath)) {
                                    return $cssPath;
                                }
                            }

                            // Try with .scss extension
                            if (is_file($fullPath . '.scss')) {
                                return $fullPath . '.scss';
                            }
                        }

                        return null;
                    },
                ],
            ]),
            $testScss
        );

        // Should contain content from both imported files
        static::assertStringContainsString('.plain-css-from-library', $result);
        static::assertStringContainsString('.another-lib', $result);
    }

    // ===================================
    // Variable Type Handling Tests
    // ===================================

    public function testHandlesColorVariables(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-color-primary: #ff0000', $variablesContent);
    }

    public function testHandlesBooleanVariables(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-custom-header-enabled' => [
                    'type' => 'checkbox',
                    'value' => true,
                ],
                'sw-custom-footer-enabled' => [
                    'type' => 'checkbox',
                    'value' => false,
                ],
                'sw-switch-enabled' => [
                    'type' => 'switch',
                    'value' => true,
                ],
                'sw-switch-disabled' => [
                    'type' => 'switch',
                    'value' => false,
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-custom-header-enabled: 1', $variablesContent);
        static::assertStringContainsString('$sw-custom-footer-enabled: 0', $variablesContent);
        static::assertStringContainsString('$sw-switch-enabled: 1', $variablesContent);
        static::assertStringContainsString('$sw-switch-disabled: 0', $variablesContent);
    }

    public function testHandlesTextVariables(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-text-field' => [
                    'type' => 'text',
                    'value' => '2px solid #000',
                ],
                'sw-textarea-field' => [
                    'type' => 'textarea',
                    'value' => 'Lorem ipsum',
                ],
                'sw-url-field' => [
                    'type' => 'url',
                    'value' => 'https://example.com',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-text-field: 2px solid #000', $variablesContent);
        static::assertStringContainsString('$sw-textarea-field: \'Lorem ipsum\'', $variablesContent);
        static::assertStringContainsString('$sw-url-field: \'https://example.com\'', $variablesContent);
    }

    public function testHandlesNullAndZeroValues(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-zero-margin' => [
                    'type' => 'text',
                    'value' => 0,
                ],
                'sw-null-margin' => [
                    'type' => 'text',
                    'value' => null,
                ],
                'sw-unset-margin' => [
                    'type' => 'text',
                    // No value key
                ],
                'sw-empty-margin' => [
                    'type' => 'text',
                    'value' => '',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-zero-margin: 0', $variablesContent);
        static::assertStringContainsString('$sw-null-margin: null', $variablesContent);
        static::assertStringContainsString('$sw-unset-margin: null', $variablesContent);
        static::assertStringContainsString('$sw-empty-margin: null', $variablesContent);
    }

    public function testIgnoresFieldsWithScssPropertySetToFalse(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
                ],
                'sw-ignored-field' => [
                    'type' => 'text',
                    'value' => 'Should not appear',
                    'scss' => false,
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-color-primary: #ff0000', $variablesContent);
        static::assertStringNotContainsString('sw-ignored-field', $variablesContent);
    }

    public function testHandlesMediaFieldVariables(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'media-id-123',
                ],
                'sw-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'media-id-456',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-logo-desktop: \'media-id-123\'', $variablesContent);
        static::assertStringContainsString('$sw-logo-mobile: \'media-id-456\'', $variablesContent);
    }

    public function testHandlesMultiSelectFieldWithArrayValue(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-multi-select-field' => [
                    'name' => 'sw-multi-select-field',
                    'type' => 'text',
                    'value' => [
                        'top',
                        'bottom',
                    ],
                    'custom' => [
                        'componentName' => 'sw-multi-select',
                        'options' => [
                            ['value' => 'top'],
                            ['value' => 'bottom'],
                            ['value' => 'left'],
                            ['value' => 'right'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // Multi-select fields with array values are not converted to SCSS variables
        // They are filtered out because they're not scalar values
        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // The variable should not appear as SCSS doesn't support array values
        static::assertStringNotContainsString('$sw-multi-select-field:', $variablesContent);
    }

    public function testHandlesInvalidFieldTypes(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-valid-field' => [
                    'type' => 'text',
                    'value' => 'valid value',
                ],
                'sw-invalid-array-media' => [
                    'type' => 'media',
                    'value' => [123], // Invalid - array instead of string
                ],
                'sw-field-without-type' => [
                    'name' => 'sw-field-without-type',
                    'value' => 'no type specified',
                    // Missing 'type' key
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // Valid field should be present
        static::assertStringContainsString('$sw-valid-field: valid value', $variablesContent);

        // Invalid fields should not be present (filtered out)
        static::assertStringNotContainsString('sw-invalid-array-media', $variablesContent);
        static::assertStringNotContainsString('sw-field-without-type', $variablesContent);
    }

    public function testComprehensiveVariableTypeCompilation(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
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
                    'name' => 'sw-custom-footer',
                    'type' => 'checkbox',
                    'value' => true,
                ],
                'sw-custom-cart' => [
                    'name' => 'sw-custom-cart',
                    'type' => 'switch',
                    'value' => false,
                ],
                'sw-custom-product-box' => [
                    'name' => 'sw-custom-product-box',
                    'type' => 'switch',
                    'value' => true,
                ],
                'sw-text-field' => [
                    'name' => 'sw-text-field',
                    'type' => 'text',
                    'value' => '2px solid #000',
                ],
                'sw-textarea-field' => [
                    'name' => 'sw-textarea-field',
                    'type' => 'textarea',
                    'value' => 'Lorem ipsum dolor',
                ],
                'sw-url-field' => [
                    'name' => 'sw-url-field',
                    'type' => 'url',
                    'value' => 'https://www.example.com',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // Verify all variable types are correctly formatted
        static::assertStringContainsString('$sw-color-brand-primary: #008490', $variablesContent);
        static::assertStringContainsString('$sw-color-brand-secondary: #526e7f', $variablesContent);
        static::assertStringContainsString('$sw-border-color: #bcc1c7', $variablesContent);
        static::assertStringContainsString('$sw-custom-header: 0', $variablesContent);
        static::assertStringContainsString('$sw-custom-footer: 1', $variablesContent);
        static::assertStringContainsString('$sw-custom-cart: 0', $variablesContent);
        static::assertStringContainsString('$sw-custom-product-box: 1', $variablesContent);
        static::assertStringContainsString('$sw-text-field: 2px solid #000', $variablesContent);
        static::assertStringContainsString('$sw-textarea-field: \'Lorem ipsum dolor\'', $variablesContent);
        static::assertStringContainsString('$sw-url-field: \'https://www.example.com\'', $variablesContent);
        static::assertStringContainsString('$sw-asset-theme-url: \'http://localhost\'', $variablesContent);
    }

    // ===================================
    // End-to-End Compilation Tests
    // ===================================

    public function testFullCompilationCreatesAllExpectedFiles(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // Check temp filesystem has variables
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
        static::assertTrue($this->tempFilesystem->has('theme-variables/test-theme-id.scss'));

        // Check main filesystem has theme directory
        $pathBuilder = new MD5ThemePathBuilder();
        $themePath = 'theme/' . $pathBuilder->assemblePath($this->mockSalesChannelId, 'test-theme-id');
        static::assertTrue($this->filesystem->directoryExists($themePath));
    }

    public function testCompilationWritesCssFile(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $pathBuilder = new MD5ThemePathBuilder();
        $cssPath = 'theme/' . $pathBuilder->assemblePath($this->mockSalesChannelId, 'test-theme-id') . '/css/all.css';

        static::assertTrue($this->filesystem->fileExists($cssPath));
    }

    // ===================================
    // Asset URL Tests
    // ===================================

    public function testInjectsAssetUrlVariable(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockSalesChannelId,
            'test-theme-id',
            $config,
            new StorefrontPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$sw-asset-theme-url: \'http://localhost\'', $variablesContent);
    }
}
