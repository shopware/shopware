<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;

/**
 * @deprecated tag:v6.8.0 - Will be removed in v6.8.0, use ThemeFixtures instead
 *
 * @internal
 *
 * @phpstan-type ThemeFixture iterable<array{
 *     ids: array<string, mixed>,
 *     themeCollection: ThemeCollection,
 *     expected?: array<string, mixed>,
 *     expectedNotTranslated?: array<string, mixed>|null,
 *     expectedStructured?: array<string, mixed>,
 *     expectedStructuredNotTranslated?: array<string, mixed>
 * }>
 */
class ThemeFixtures_6_7
{
    /**
     * @return array<string, mixed>
     */
    public static function getThemeJsonConfig(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'sw-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],

                'sw-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],

                'sw-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],

                'sw-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],

                'sw-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],

                'sw-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],

                'sw-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],

                'sw-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],

                'sw-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],

                'sw-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],

                'sw-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],

                'sw-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],

                'sw-color-price' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 100,
                ],

                'sw-color-buy-button' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 200,
                ],

                'sw-color-buy-button-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 300,
                ],

                'sw-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],

                'sw-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],

                'sw-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],

                'sw-logo-share' => [
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],

                'sw-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return ThemeFixture
     */
    public static function getThemeCollectionForThemeConfiguration(): iterable
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $baseThemeId = Uuid::randomHex();

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'labels' => [
                                'fields.extend-parent-custom-config' => 'EN',
                            ],
                            'helpTexts' => [
                                'fields.extend-parent-custom-config' => 'EN Helptext',
                            ],
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                                'config' => self::getThemeJsonConfig(),
                                'fields' => [
                                    'extend-parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'labels' => [
                                'fields.parent-custom-config' => 'EN',
                            ],
                            'helpTexts' => [
                                'fields.parent-custom-config' => 'EN Helptext',
                            ],
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@Storefront',
                                ],
                                'fields' => [
                                    'parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields7(),
                'configInheritance' => self::getExtractedConfigInheritance(),
                'config' => self::getExtractedConfig1(),
                'currentFields' => self::getExtractedCurrentFields5(),
                'baseThemeFields' => self::getExtractedBaseThemeFields5(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs10(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'labels' => [
                                'testlabel',
                            ],
                            'helpTexts' => [
                                'testHelp',
                            ],
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                                'config' => self::getThemeJsonConfig(),
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields1(),
                'configInheritance' => self::getExtractedConfigInheritance(),
                'config' => self::getExtractedConfig1(),
                'currentFields' => self::getExtractedCurrentFields1(),
                'baseThemeFields' => self::getExtractedBaseThemeFields1(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs1(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'labels' => [],
                            'helpTexts' => [
                                'firstHelp',
                                'testHelp',
                            ],
                            'baseConfig' => [
                                'fields' => [
                                    'first' => [],
                                    'test' => [],
                                ],
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields3(),
                'configInheritance' => self::getExtractedConfigInheritance(),
                'currentFields' => self::getExtractedCurrentFields2(),
                'baseThemeFields' => self::getExtractedBaseThemeFields2(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs3(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'fields' => false,
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields5(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'fields' => [],
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'salesChannels' => new SalesChannelCollection(),
                            'technicalName' => 'Test',
                            'configValues' => [],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields5(),
                'currentFields' => self::getExtractedBaseThemeFields8(),
                'baseThemeFields' => self::getExtractedCurrentFields8(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
            ],
        ];

        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    (new ThemeEntity())->assign(
                        [
                            'id' => $themeId,
                            'technicalName' => 'Theme',
                            '_uniqueIdentifier' => $themeId,
                            'baseConfig' => [
                                'fields' => [
                                    'sw-color-brand-primary' => [
                                        'value' => '#adbd00',
                                    ],
                                    'test-something-with-options' => [
                                        'type' => 'text',
                                        'editable' => true,
                                        'block' => 'media',
                                        'order' => 600,
                                        'value' => 'Hello',
                                        'fullWidth' => null,
                                        'custom' => [
                                            'componentName' => 'sw-single-select',
                                            'options' => [
                                                [
                                                    'value' => 'Hello',
                                                ],
                                                [
                                                    'value' => 'World',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]
                    ),
                    (new ThemeEntity())->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                            'baseConfig' => self::getThemeJsonConfig(),
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields10(),
                'currentFields' => self::getExtractedCurrentFields6(),
                'baseThemeFields' => self::getExtractedBaseThemeFields6(),
                'name' => 'test',
                'themeTechnicalName' => 'Theme',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabsNameTheme(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => 'sw-color-brand-primary',
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => 'sw-color-brand-secondary',
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => 'sw-border-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => 'sw-background-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => 'sw-color-success',
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => 'sw-color-info',
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => 'sw-color-warning',
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => 'sw-color-danger',
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => 'sw-font-family-base',
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => 'sw-text-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => 'sw-font-family-headline',
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => 'sw-headline-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => 'sw-color-price',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => 'sw-color-buy-button',
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => 'sw-color-buy-button-text',
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => 'sw-logo-desktop',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => 'sw-logo-tablet',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => 'sw-logo-mobile',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => 'sw-logo-share',
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => 'sw-logo-favicon',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => 'test',
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function getExtractedConfigInheritance(): array
    {
        return [
            0 => '@ParentTheme',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedConfig1(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'sw-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],
                'sw-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],
                'sw-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],
                'sw-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],
                'sw-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],
                'sw-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],
                'sw-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],
                'sw-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],
                'sw-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],
                'sw-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],
                'sw-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],
                'sw-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],
                'sw-color-price' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 100,
                ],
                'sw-color-buy-button' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 200,
                ],
                'sw-color-buy-button-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 300,
                ],
                'sw-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],
                'sw-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],
                'sw-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],
                'sw-logo-share' => [
                    'type' => 'media',
                    'value' => null,
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],
                'sw-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields3(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'first' => [
                'extensions' => [
                ],
                'name' => 'first',
                'label' => null,
                'helpText' => null,
                'value' => null,
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields2(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'first' => [
                'isInherited' => null,
                'value' => null,
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields2(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'first' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields5(): array
    {
        return [
            ...self::getExtractedFieldsSub1(),
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields3(): array
    {
        return [
            ...self::getExtractedCurrentFields1(),
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields3(): array
    {
        return [
            ...self::getExtractedBaseThemeFields1(),
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs5(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => self::getExtractedSectionsMedia(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields7(): array
    {
        return [...self::getExtractedFields1(), ...[
            'parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'parent-custom-config',
                'label' => 'EN',
                'helpText' => 'EN Helptext',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'extend-parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'extend-parent-custom-config',
                'label' => 'EN',
                'helpText' => 'EN Helptext',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields10(): array
    {
        $fields = self::getExtractedFields9();

        foreach ($fields as $key => $field) {
            if ($field['editable'] === 1) {
                $fields[$key]['editable'] = true;
            }

            if ($field['fullWidth'] === 1) {
                $fields[$key]['fullWidth'] = true;
            }
        }

        $fields['test-something-with-options'] = [
            'name' => 'test-something-with-options',
            'extensions' => [],
            'label' => null,
            'helpText' => null,
            'type' => 'text',
            'value' => 'Hello',
            'editable' => true,
            'block' => 'media',
            'section' => null,
            'tab' => null,
            'order' => 600,
            'sectionOrder' => null,
            'blockOrder' => null,
            'tabOrder' => null,
            'custom' => [
                'componentName' => 'sw-single-select',
                'options' => [
                    [
                        'value' => 'Hello',
                    ],
                    [
                        'value' => 'World',
                    ],
                ],
            ],
            'scss' => null,
            'fullWidth' => null,
        ];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields5(): array
    {
        return [...self::getExtractedCurrentFields1(), ...[
            'parent-custom-config' => [
                'value' => null,
                'isInherited' => true,
            ],
            'extend-parent-custom-config' => [
                'value' => '20',
                'isInherited' => false,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields6(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#adbd00',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => true,
                'value' => null,
            ],
            'test-something-with-options' => [
                'value' => 'Hello',
                'isInherited' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields8(): array
    {
        $currentFields = self::getExtractedCurrentFields6();

        $currentFields['sw-color-brand-primary'] = [
            'isInherited' => true,
            'value' => null,
        ];

        $currentFields['test'] = [
            'isInherited' => null,
            'value' => [
                0 => 'no_test',
            ],
        ];

        unset($currentFields['test-something-with-options']);

        return $currentFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields5(): array
    {
        return [...self::getExtractedBaseThemeFields1(), ...[
            'parent-custom-config' => [
                'isInherited' => 0,
                'value' => 20,
            ],
            'extend-parent-custom-config' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields6(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => false,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => false,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => false,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => false,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => false,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => false,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => false,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'test-something-with-options' => [
                'isInherited' => true,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields8(): array
    {
        $baseThemeFields = self::getExtractedBaseThemeFields6();

        $baseThemeFields['test'] = [
            'isInherited' => true,
            'value' => null,
        ];

        unset($baseThemeFields['test-something-with-options']);

        return $baseThemeFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs10(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'parent-custom-config' => [
                                        'label' => 'EN',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.parent-custom-config.label',
                                        'helpText' => 'EN Helptext',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'extend-parent-custom-config' => [
                                        'label' => 'EN',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.extend-parent-custom-config.label',
                                        'helpText' => 'EN Helptext',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.extend-parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabsNameTheme(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.theme.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.label',
                                'fields' => [
                                    'sw-color-brand-primary' => [
                                        'label' => 'sw-color-brand-primary',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-primary.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-primary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-brand-secondary' => [
                                        'label' => 'sw-color-brand-secondary',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-secondary.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-secondary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-border-color' => [
                                        'label' => 'sw-border-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-border-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-border-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-background-color' => [
                                        'label' => 'sw-background-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-background-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-background-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.label',
                                'fields' => [
                                    'sw-color-success' => [
                                        'label' => 'sw-color-success',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-success.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-success.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-info' => [
                                        'label' => 'sw-color-info',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-info.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-info.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-warning' => [
                                        'label' => 'sw-color-warning',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-warning.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-warning.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-danger' => [
                                        'label' => 'sw-color-danger',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-danger.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-danger.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.theme.default.typography.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.typography.default.label',
                                'fields' => [
                                    'sw-font-family-base' => [
                                        'label' => 'sw-font-family-base',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-base.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-base.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-text-color' => [
                                        'label' => 'sw-text-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-text-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-text-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-font-family-headline' => [
                                        'label' => 'sw-font-family-headline',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-headline.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-headline.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-headline-color' => [
                                        'label' => 'sw-headline-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-headline-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-headline-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.label',
                                'fields' => [
                                    'sw-color-price' => [
                                        'label' => 'sw-color-price',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-price.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-price.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-buy-button' => [
                                        'label' => 'sw-color-buy-button',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-buy-button-text' => [
                                        'label' => 'sw-color-buy-button-text',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button-text.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button-text.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.theme.default.media.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.media.default.label',
                                'fields' => [
                                    'sw-logo-desktop' => [
                                        'label' => 'sw-logo-desktop',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-desktop.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-desktop.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-tablet' => [
                                        'label' => 'sw-logo-tablet',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-tablet.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-tablet.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-mobile' => [
                                        'label' => 'sw-logo-mobile',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-mobile.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-mobile.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-share' => [
                                        'label' => 'sw-logo-share',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-share.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-share.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-logo-favicon' => [
                                        'label' => 'sw-logo-favicon',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-favicon.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-favicon.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test-something-with-options' => [
                                        'type' => 'text',
                                        'label' => 'test-something-with-options',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.helpText',
                                        'fullWidth' => null,
                                        'custom' => [
                                            'componentName' => 'sw-single-select',
                                            'options' => [
                                                [
                                                    'value' => 'Hello',
                                                    'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.0.label',
                                                ],
                                                [
                                                    'value' => 'World',
                                                    'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.1.label',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs3(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'first' => [
                                        'label' => 'first',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.first.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.first.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs1(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields2(): array
    {
        return [...self::getExtractedFieldsSub1(), ...[
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFieldsSub1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields9(): array
    {
        $fields = self::getExtractedFieldsSub1();

        $fields['sw-color-brand-primary']['value'] = '#adbd00';

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsThemeColors(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.label',
                'fields' => [
                    'sw-color-brand-primary' => [
                        'label' => 'sw-color-brand-primary',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-brand-secondary' => [
                        'label' => 'sw-color-brand-secondary',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-border-color' => [
                        'label' => 'sw-border-color',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-background-color' => [
                        'label' => 'sw-background-color',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsStatusColors(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.label',
                'fields' => [
                    'sw-color-success' => [
                        'label' => 'sw-color-success',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-info' => [
                        'label' => 'sw-color-info',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-warning' => [
                        'label' => 'sw-color-warning',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-danger' => [
                        'label' => 'sw-color-danger',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsTypography(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.typography.default.label',
                'fields' => [
                    'sw-font-family-base' => [
                        'label' => 'sw-font-family-base',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-text-color' => [
                        'label' => 'sw-text-color',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-font-family-headline' => [
                        'label' => 'sw-font-family-headline',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-headline-color' => [
                        'label' => 'sw-headline-color',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsECommerce(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.label',
                'fields' => [
                    'sw-color-price' => [
                        'label' => 'sw-color-price',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-buy-button' => [
                        'label' => 'sw-color-buy-button',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-buy-button-text' => [
                        'label' => 'sw-color-buy-button-text',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsMedia(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.media.default.label',
                'fields' => [
                    'sw-logo-desktop' => [
                        'label' => 'sw-logo-desktop',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-tablet' => [
                        'label' => 'sw-logo-tablet',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-mobile' => [
                        'label' => 'sw-logo-mobile',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-share' => [
                        'label' => 'sw-logo-share',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-logo-favicon' => [
                        'label' => 'sw-logo-favicon',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsMediaNoHelpTexts(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.media.default.label',
                'fields' => [
                    'sw-logo-desktop' => [
                        'label' => 'sw-logo-desktop',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-tablet' => [
                        'label' => 'sw-logo-tablet',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-mobile' => [
                        'label' => 'sw-logo-mobile',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-share' => [
                        'label' => 'sw-logo-share',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-logo-favicon' => [
                        'label' => 'sw-logo-favicon',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }
}
