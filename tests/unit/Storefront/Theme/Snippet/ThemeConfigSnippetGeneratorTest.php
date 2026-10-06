<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Snippet\ThemeConfigSnippetGenerator;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigSnippetGenerator::class)]
class ThemeConfigSnippetGeneratorTest extends TestCase
{
    private ThemeConfigSnippetGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ThemeConfigSnippetGenerator();
    }

    public function testThemeWithoutThemeJsonYieldsNoSnippets(): void
    {
        $configuration = new StorefrontPluginConfiguration('SwagTheme');

        static::assertSame([], $this->generator->generate($configuration));
    }

    public function testThemeWithoutLegacyTranslationsYieldsNoSnippets(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'sw-color-brand-primary' => ['type' => 'color', 'value' => '#0042a0', 'block' => 'themeColors'],
            ],
            'blocks' => [
                'themeColors' => [],
            ],
        ]);

        static::assertSame([], $this->generator->generate($configuration));
    }

    public function testFieldTranslationsAreKeyedByTheirHierarchy(): void
    {
        $configuration = $this->createConfiguration([
            'tabs' => [
                'colorTab' => ['label' => ['en-GB' => 'Colours', 'de-DE' => 'Farben']],
            ],
            'blocks' => [
                'primaryColors' => ['label' => ['en-GB' => 'Primary colours', 'de-DE' => 'Primärfarben']],
            ],
            'sections' => [
                'home' => ['label' => ['en-GB' => 'Home', 'de-DE' => 'Startseite']],
            ],
            'fields' => [
                'sw-color-primary' => [
                    'type' => 'color',
                    'tab' => 'colorTab',
                    'block' => 'primaryColors',
                    'section' => 'home',
                    'label' => ['en-GB' => 'Primary colour', 'de-DE' => 'Primärfarbe'],
                    'helpText' => ['en-GB' => 'Used for buttons', 'de-DE' => 'Für Buttons'],
                ],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'colorTab' => [
                            'label' => 'Colours',
                            'primaryColors' => [
                                'label' => 'Primary colours',
                                'home' => [
                                    'label' => 'Home',
                                    'sw-color-primary' => [
                                        'label' => 'Primary colour',
                                        'helpText' => 'Used for buttons',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'de-DE' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'colorTab' => [
                            'label' => 'Farben',
                            'primaryColors' => [
                                'label' => 'Primärfarben',
                                'home' => [
                                    'label' => 'Startseite',
                                    'sw-color-primary' => [
                                        'label' => 'Primärfarbe',
                                        'helpText' => 'Für Buttons',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    public function testUngroupedFieldsLandUnderTheDefaultHierarchy(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'sw-logo' => [
                    'type' => 'media',
                    'label' => ['en-GB' => 'Logo'],
                ],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => [
                            'default' => [
                                'default' => [
                                    'sw-logo' => ['label' => 'Logo'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    public function testSelectOptionsAreKeyedByTheirIndex(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'sw-layout' => [
                    'type' => 'text',
                    'block' => 'layout',
                    'custom' => [
                        'componentName' => 'sw-single-select',
                        'options' => [
                            ['value' => 'boxed', 'label' => ['en-GB' => 'Boxed']],
                            ['value' => 'full', 'label' => ['en-GB' => 'Full width']],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => [
                            'layout' => [
                                'default' => [
                                    'sw-layout' => [
                                        '0' => ['label' => 'Boxed'],
                                        '1' => ['label' => 'Full width'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    public function testBlockLabelIsRepeatedForEveryTabItAppearsIn(): void
    {
        $configuration = $this->createConfiguration([
            'blocks' => [
                'shared' => ['label' => ['en-GB' => 'Shared block']],
            ],
            'fields' => [
                'field-a' => ['tab' => 'tabA', 'block' => 'shared'],
                'field-b' => ['tab' => 'tabB', 'block' => 'shared'],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'tabA' => ['shared' => ['label' => 'Shared block']],
                        'tabB' => ['shared' => ['label' => 'Shared block']],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    public function testEveryLocaleOfTheThemeJsonGetsItsOwnSnippetFile(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'sw-color' => ['label' => ['de-DE' => 'Farbe', 'de-CH' => 'Farbe (CH)']],
                'sw-size' => ['label' => ['de-DE' => 'Grösse']],
            ],
        ]);

        static::assertSame([
            'de-DE' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => [
                            'default' => [
                                'default' => [
                                    'sw-color' => ['label' => 'Farbe'],
                                    'sw-size' => ['label' => 'Grösse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'de-CH' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => [
                            'default' => [
                                'default' => [
                                    'sw-color' => ['label' => 'Farbe (CH)'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    public function testFileNameAndEncodingAreSharedByEveryWriter(): void
    {
        static::assertSame('de-DE.json', $this->generator->fileName('de-DE'));
        static::assertSame("{\n    \"sw-theme\": {\n        \"label\": \"Größe/Breite\"\n    }\n}\n", $this->generator->encode(['sw-theme' => ['label' => 'Größe/Breite']]));
    }

    public function testMalformedTranslationsAreSkipped(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'plain-string-label' => ['label' => 'Not localized'],
                'non-string-value' => ['label' => ['en-GB' => ['nested']]],
                'not-an-array' => 'broken',
                'valid' => ['label' => ['en-GB' => 'Valid']],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => [
                            'default' => [
                                'default' => [
                                    'valid' => ['label' => 'Valid'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($configuration));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConfiguration(array $config): StorefrontPluginConfiguration
    {
        $configuration = new StorefrontPluginConfiguration('SwagTheme');
        $configuration->setThemeJson(['name' => 'SwagTheme', 'config' => $config]);

        return $configuration;
    }
}
