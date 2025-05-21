<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;

/**
 * @internal
 */
class ThemeMergedConfigBuilderFixtures
{
    /**
     * @return iterable<array{
     *     ids: array<string, mixed>,
     *     themeCollection: ThemeCollection,
     *     expected?: array<string, mixed>,
     *     expectedNotTranslated?: array<string, mixed>|null,
     *     expectedStructured?: array<string, mixed>,
     *     expectedStructuredNotTranslated?: array<string, mixed>
     * }>
     */
    public static function getTestCases(): iterable
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
                                'config' => ThemeFixtures::getThemeJsonConfig(),
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
                'fields' => ThemeFixtures::getExtractedFields7(),
                'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                'config' => ThemeFixtures::getExtractedConfig1(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields5(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields5(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs10(),
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
                                'config' => ThemeFixtures::getThemeJsonConfig(),
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
                'fields' => ThemeFixtures::getExtractedFields1(),
                'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                'config' => ThemeFixtures::getExtractedConfig1(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields1(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields1(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs1(),
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
                'fields' => ThemeFixtures::getExtractedFields3(),
                'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields2(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields2(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs3(),
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
                'fields' => ThemeFixtures::getExtractedFields2(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs5(),
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
                'fields' => ThemeFixtures::getExtractedFields5(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs5(),
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
                'fields' => ThemeFixtures::getExtractedFields2(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs5(),
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
                'fields' => ThemeFixtures::getExtractedFields2(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs5(),
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
                'fields' => ThemeFixtures::getExtractedFields5(),
                'currentFields' => ThemeFixtures::getExtractedBaseThemeFields8(),
                'baseThemeFields' => ThemeFixtures::getExtractedCurrentFields8(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabs5(),
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
                                                ], [
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
                            'baseConfig' => ThemeFixtures::getThemeJsonConfig(),
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => ThemeFixtures::getExtractedFields10(),
                'currentFields' => ThemeFixtures::getExtractedCurrentFields6(),
                'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields6(),
                'name' => 'test',
                'themeTechnicalName' => 'Theme',
            ],
            'expectedStructured' => [
                'tabs' => ThemeFixtures::getExtractedTabsNameTheme(),
            ],
        ];
    }
}
