<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Snippet\ThemeConfigSnippetGenerator;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigSnippetGenerator::class)]
class ThemeConfigSnippetGeneratorTest extends TestCase
{
    private ThemeConfigSnippetGenerator $generator;

    /**
     * @var array<string, StorefrontPluginConfiguration>
     */
    private array $parentThemes = [];

    protected function setUp(): void
    {
        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getByTechnicalName')->willReturnCallback(fn (string $name): ?StorefrontPluginConfiguration => $this->parentThemes[$name] ?? null);

        $this->generator = new ThemeConfigSnippetGenerator($registry);
    }

    public function testChildThemeCanRelabelInheritedGroupsWithoutOwnFields(): void
    {
        $this->parentThemes['Storefront'] = $this->createConfiguration([
            'fields' => [
                'sw-color-brand-primary' => ['type' => 'color', 'tab' => 'colors', 'block' => 'themeColors', 'section' => 'brand'],
            ],
        ], 'Storefront');

        $child = $this->createConfiguration([
            'tabs' => ['colors' => ['label' => ['en-GB' => 'Brand colours']]],
            'blocks' => ['themeColors' => ['label' => ['en-GB' => 'Our colours']]],
            'sections' => ['brand' => ['label' => ['en-GB' => 'Brand']]],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'colors' => [
                            'label' => 'Brand colours',
                            'themeColors' => [
                                'label' => 'Our colours',
                                'brand' => ['label' => 'Brand'],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->generator->generate($child));
        static::assertSame([], $this->generator->findUnplaceableGroups($child));
    }

    public function testChildThemeCanRelabelAnInheritedFieldWithoutRepeatingItsPosition(): void
    {
        $this->parentThemes['Storefront'] = $this->createConfiguration([
            'fields' => [
                'sw-logo' => ['type' => 'media', 'tab' => 'media', 'block' => 'logos'],
            ],
        ], 'Storefront');

        $child = $this->createConfiguration([
            'fields' => [
                'sw-logo' => ['label' => ['en-GB' => 'Shop logo'], 'helpText' => ['en-GB' => 'Shown in the header']],
            ],
        ]);

        static::assertSame([
            'en-GB' => [
                'sw-theme' => [
                    'SwagTheme' => [
                        'media' => ['logos' => ['default' => ['sw-logo' => ['label' => 'Shop logo', 'helpText' => 'Shown in the header']]]],
                    ],
                ],
            ],
        ], $this->generator->generate($child));
    }

    public function testExplicitConfigInheritanceIsFollowedInOrder(): void
    {
        $this->parentThemes['Storefront'] = $this->createConfiguration([
            'fields' => ['sw-logo' => ['type' => 'media', 'tab' => 'storefrontTab']],
        ], 'Storefront');
        $this->parentThemes['SwagParentTheme'] = $this->createConfiguration([
            'fields' => ['sw-logo' => ['type' => 'media', 'tab' => 'parentTab']],
        ], 'SwagParentTheme');

        $child = $this->createConfiguration([
            'fields' => ['sw-logo' => ['label' => ['en-GB' => 'Logo']]],
        ]);
        $child->setConfigInheritance(['@Storefront', '@SwagParentTheme']);

        static::assertSame(
            ['parentTab'],
            array_keys($this->generator->generate($child)['en-GB']['sw-theme']['SwagTheme']),
        );
    }

    public function testUnknownParentThemesAreIgnored(): void
    {
        $child = $this->createConfiguration([
            'fields' => ['sw-logo' => ['label' => ['en-GB' => 'Logo']]],
        ]);
        $child->setConfigInheritance(['@Missing']);

        static::assertSame(
            ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo']]]]],
            $this->generator->generate($child)['en-GB']['sw-theme']['SwagTheme'],
        );
    }

    public function testGroupLabelsNoFieldUsesAreReportedInsteadOfSilentlyDropped(): void
    {
        $configuration = $this->createConfiguration([
            'blocks' => [
                'ghost' => ['label' => ['en-GB' => 'Nobody uses me']],
                'logos' => ['label' => ['en-GB' => 'Logos']],
            ],
            'sections' => ['lost' => ['label' => ['en-GB' => 'Lost']]],
            'fields' => ['sw-logo' => ['type' => 'media', 'block' => 'logos']],
        ]);

        static::assertSame(['blocks.ghost', 'sections.lost'], $this->generator->findUnplaceableGroups($configuration));
        static::assertSame(
            ['default' => ['logos' => ['label' => 'Logos']]],
            $this->generator->generate($configuration)['en-GB']['sw-theme']['SwagTheme'],
        );
    }

    public function testTabLabelsNeedNoFieldToBePlaced(): void
    {
        $configuration = $this->createConfiguration([
            'tabs' => ['extras' => ['label' => ['en-GB' => 'Extras']]],
        ]);

        static::assertSame(
            ['en-GB' => ['sw-theme' => ['SwagTheme' => ['extras' => ['label' => 'Extras']]]]],
            $this->generator->generate($configuration),
        );
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

    public function testLocaleKeysThatAreNoLocaleAreSkipped(): void
    {
        $configuration = $this->createConfiguration([
            'fields' => [
                'sw-logo' => [
                    'label' => [
                        '../../../translation/locale/en-GB/Platform/storefront' => 'escaped',
                        'DE' => 'upper case language',
                        'german' => 'no locale',
                        'en-GB.base' => 'file suffix smuggled in',
                        'de-DE' => 'Logo DE',
                        'zh-Hant-TW' => 'Logo TW',
                    ],
                ],
            ],
        ]);

        static::assertSame(['de-DE', 'zh-Hant-TW'], array_keys($this->generator->generate($configuration)));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createConfiguration(array $config, string $technicalName = 'SwagTheme'): StorefrontPluginConfiguration
    {
        $configuration = new StorefrontPluginConfiguration($technicalName);
        $configuration->setThemeJson(['name' => $technicalName, 'config' => $config]);

        return $configuration;
    }
}
