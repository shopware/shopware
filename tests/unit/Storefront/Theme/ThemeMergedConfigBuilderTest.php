<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures;

/**
 * @internal
 */
#[CoversClass(ThemeService::class)]
class ThemeMergedConfigBuilderTest extends TestCase
{
    private StorefrontPluginRegistry&MockObject $storefrontPluginRegistryMock;

    /** @var EntityRepository<ThemeCollection>&MockObject */
    private EntityRepository&MockObject $themeRepositoryMock;

    private ThemeMergedConfigBuilder $mergedConfigBuilder;

    private Context $context;

    protected function setUp(): void
    {
        $this->storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $this->themeRepositoryMock = $this->createMock(EntityRepository::class);

        $this->context = Context::createDefaultContext();

        $this->mergedConfigBuilder = new ThemeMergedConfigBuilder(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
        );
    }

    public function testGetThemeConfigurationNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection(
                    [
                        (new ThemeEntity())->assign(
                            [
                                '_uniqueIdentifier' => 'no',
                                'salesChannels' => new SalesChannelCollection(),
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage(\sprintf('Could not find theme with id "%s"', $themeId));

        $this->mergedConfigBuilder->getThemeConfiguration($themeId, false, $this->context);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfiguration(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfiguration($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationNoTranslation(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        if ($expectedNotTranslated !== null) {
            $expected = $expectedNotTranslated;
        }

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfiguration($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationStructured(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationStructuredFields($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationStructuredNoTranslation(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        if ($expectedStructuredNotTranslated !== null) {
            $expectedStructured = $expectedStructuredNotTranslated;
        }

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationStructuredFields($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @return array{array{
     *     ids: array<string, mixed>,
     *     themeCollection: ThemeCollection,
     *     expected?: array<string, mixed>,
     *     expectedNotTranslated?: array<string, mixed>,
     *     expectedStructured?: array<string, mixed>,
     *     expectedStructuredNotTranslated?: array<string, mixed>
     * }}
     */
    public static function getThemeCollectionForThemeConfiguration(): array
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $baseThemeId = Uuid::randomHex();

        return [
            [
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
                                            'label' => [
                                                'de-DE' => 'DE',
                                                'en-GB' => 'EN',
                                            ],
                                            'value' => '20',
                                            'editable' => true,
                                            'helpText' => [
                                                'de-DE' => 'De Helptext',
                                                'en-GB' => 'EN Helptext',
                                            ],
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
                                            'label' => [
                                                'de-DE' => 'DE',
                                                'en-GB' => 'EN',
                                            ],
                                            'value' => '20',
                                            'editable' => true,
                                            'helpText' => [
                                                'de-DE' => 'De Helptext',
                                                'en-GB' => 'EN Helptext',
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        ),
                    ]
                ),
                'expected' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields7(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'config' => ThemeFixtures::getExtractedConfig1(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields5(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields5(),
                ],
                'expectedNotTranslated' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields8(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'config' => ThemeFixtures::getExtractedConfig2(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields5(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields5(),
                ],
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs10(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs11(),
                ],
            ],
            [
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
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields1(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'config' => ThemeFixtures::getExtractedConfig1(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields1(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields1(),
                ],
                'expectedNotTranslated' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields2(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'config' => ThemeFixtures::getExtractedConfig2(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields1(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields1(),
                ],
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs1(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs2(),
                ],
            ],
            [
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
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields3(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields2(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields2(),
                ],
                'expectedNotTranslated' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields4(),
                    'configInheritance' => ThemeFixtures::getExtractedConfigInheritance(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields2(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields2(),
                ],
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs3(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs4(),
                ],
            ],
            [
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
                    'fields' => ThemeFixtures::getExtractedFields5(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs5(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs6(),
                ],
            ],
            [
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
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs5(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs6(),
                ],
            ],
            [
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
                    'fields' => ThemeFixtures::getExtractedFields5(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs5(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs6(),
                ],
            ],
            [
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
                    'fields' => ThemeFixtures::getExtractedFields5(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields3(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs5(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs6(),
                ],
            ],
            [
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
                    'currentFields' => ThemeFixtures::getExtractedBaseThemeFields3(),
                    'baseThemeFields' => ThemeFixtures::getExtractedCurrentFields3(),
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs5(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs6(),
                ],
            ],
            [
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
                                'baseConfig' => [
                                    'blocks' => ThemeFixtures::getExtractedBlocks2(),
                                    'tabs' => ThemeFixtures::getExtractedTabs7(),
                                    'section' => ThemeFixtures::getExtractedSections1(),
                                    'fields' => [
                                        'multi' => ThemeFixtures::getMultiSelectField(),
                                        'bool' => ThemeFixtures::getBoolField(),
                                    ],
                                ],
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
                    'fields' => ThemeFixtures::getExtractedFields6(),
                    'blocks' => ThemeFixtures::getExtractedBlocks2(),
                    'tabs' => ThemeFixtures::getExtractedTabs7(),
                    'section' => ThemeFixtures::getExtractedSections1(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields4(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields4(),
                ],
                'expectedNotTranslated' => null,
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs8(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs9(),
                ],
            ],
            [
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
                                'labels' => [
                                    'blocks.media' => 'Media',
                                    'blocks.eCommerce' => 'E-Commerce',
                                    'blocks.unordered' => 'Misc',
                                    'blocks.typography' => 'Typography',
                                    'blocks.themeColors' => 'Theme colours',
                                    'blocks.statusColors' => 'Status messages',
                                    'fields.sw-color-info' => 'Information',
                                    'fields.sw-logo-share' => 'App & share icon',
                                    'fields.sw-text-color' => 'Text colour',
                                    'fields.sw-color-price' => 'Price',
                                    'fields.sw-logo-mobile' => 'Mobile',
                                    'fields.sw-logo-tablet' => 'Tablet',
                                    'fields.sw-border-color' => 'Border',
                                    'fields.sw-color-danger' => 'Error',
                                    'fields.sw-logo-desktop' => 'Desktop',
                                    'fields.sw-logo-favicon' => 'Favicon',
                                    'fields.sw-color-success' => 'Success',
                                    'fields.sw-color-warning' => 'Notice',
                                    'fields.sw-headline-color' => 'Headline colour',
                                    'fields.sw-background-color' => 'Background',
                                    'fields.sw-color-buy-button' => 'Buy button',
                                    'fields.sw-font-family-base' => 'Fonttype text',
                                    'fields.sw-color-brand-primary' => 'Primary colour',
                                    'fields.sw-font-family-headline' => 'Fonttype headline',
                                    'fields.sw-color-brand-secondary' => 'Secondary colour',
                                    'fields.sw-color-buy-button-text' => 'Buy button text',
                                ],
                                'helpTexts' => [
                                    'fields.sw-logo-mobile' => 'Displayed up to a viewport of 767px',
                                    'fields.sw-logo-tablet' => 'Displayed between a viewport of 767px to 991px',
                                    'fields.sw-logo-desktop' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                                ],
                            ]
                        ),
                    ]
                ),
                'expected' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields10(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields6(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields6(),
                ],
                'expectedNotTranslated' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields9(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields6(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields6(),
                ],
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs12(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs13(),
                ],
            ],
            [
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
                                'parentThemeId' => $parentThemeId,
                                'baseConfig' => [
                                    'fields' => [
                                        'sw-color-brand-secondary' => [
                                            'value' => '#46801a',
                                        ],
                                    ],
                                ],
                            ]
                        ),
                        (new ThemeEntity())->assign(
                            [
                                'id' => $parentThemeId,
                                'technicalName' => 'Theme',
                                '_uniqueIdentifier' => $parentThemeId,
                                'baseConfig' => [
                                    'fields' => [
                                        'sw-color-brand-primary' => [
                                            'value' => '#adbd00',
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
                                'labels' => [
                                    'blocks.media' => 'Media',
                                    'blocks.eCommerce' => 'E-Commerce',
                                    'blocks.unordered' => 'Misc',
                                    'blocks.typography' => 'Typography',
                                    'blocks.themeColors' => 'Theme colours',
                                    'blocks.statusColors' => 'Status messages',
                                    'fields.sw-color-info' => 'Information',
                                    'fields.sw-logo-share' => 'App & share icon',
                                    'fields.sw-text-color' => 'Text colour',
                                    'fields.sw-color-price' => 'Price',
                                    'fields.sw-logo-mobile' => 'Mobile',
                                    'fields.sw-logo-tablet' => 'Tablet',
                                    'fields.sw-border-color' => 'Border',
                                    'fields.sw-color-danger' => 'Error',
                                    'fields.sw-logo-desktop' => 'Desktop',
                                    'fields.sw-logo-favicon' => 'Favicon',
                                    'fields.sw-color-success' => 'Success',
                                    'fields.sw-color-warning' => 'Notice',
                                    'fields.sw-headline-color' => 'Headline colour',
                                    'fields.sw-background-color' => 'Background',
                                    'fields.sw-color-buy-button' => 'Buy button',
                                    'fields.sw-font-family-base' => 'Fonttype text',
                                    'fields.sw-color-brand-primary' => 'Primary colour',
                                    'fields.sw-font-family-headline' => 'Fonttype headline',
                                    'fields.sw-color-brand-secondary' => 'Secondary colour',
                                    'fields.sw-color-buy-button-text' => 'Buy button text',
                                ],
                                'helpTexts' => [
                                    'fields.sw-logo-mobile' => 'Displayed up to a viewport of 767px',
                                    'fields.sw-logo-tablet' => 'Displayed between a viewport of 767px to 991px',
                                    'fields.sw-logo-desktop' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                                ],
                            ]
                        ),
                    ]
                ),
                'expected' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields12(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields7(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields7(),
                ],
                'expectedNotTranslated' => [
                    'blocks' => ThemeFixtures::getExtractedBlock1(),
                    'fields' => ThemeFixtures::getExtractedFields11(),
                    'currentFields' => ThemeFixtures::getExtractedCurrentFields7(),
                    'baseThemeFields' => ThemeFixtures::getExtractedBaseThemeFields7(),
                ],
                'expectedStructured' => [
                    'tabs' => ThemeFixtures::getExtractedTabs12(),
                ],
                'expectedStructuredNotTranslated' => [
                    'tabs' => ThemeFixtures::getExtractedTabs13(),
                ],
            ],
        ];
    }
}
