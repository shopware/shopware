<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Test\Theme\fixtures\MockThemeVariablesSubscriber;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
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

    public function setUp(): void
    {
        $themeFileResolver = $this->getContainer()->get(ThemeFileResolver::class);
        $eventDispatcher = $this->getContainer()->get(EventDispatcherInterface::class);

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
            $eventDispatcher,
            $this->getContainer()->get(ThemeFileImporter::class),
            $mediaRepository,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->getContainer()->get(CacheClearer::class)
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

    public function testMediaIdsInThemeConfigGetResolvedToPaths(): void
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
                // Should be resolved to media URL
                'sw-logo-desktop' => [
                    'name' => 'sw-logo-desktop',
                    'type' => 'media',
                    'value' => $this->mockMediaId,
                ],
            ],
        ];

        $actual = $dumpVariables->invoke($this->themeCompiler, $mockConfig, $this->mockSalesChannelId);

        $re = '/\$sw-logo-desktop:\s*\'.*\/testImage\.png\'\s*;/m';
        static::assertRegExp($re, $actual);
    }
}
