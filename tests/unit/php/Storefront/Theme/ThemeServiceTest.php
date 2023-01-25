<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\ThemeService
 */
class ThemeServiceTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connectionMock;

    /**
     * @var StorefrontPluginRegistry&MockObject
     */
    private StorefrontPluginRegistry $storefrontPluginRegistryMock;

    private MockObject&EntityRepository $themeRepositoryMock;

    private MockObject&EntityRepository $themeSalesChannelRepositoryMock;

    /**
     * @var ThemeCompiler&MockObject
     */
    private ThemeCompiler $themeCompilerMock;

    /**
     * @var EventDispatcher&MockObject
     */
    private EventDispatcher $eventDispatcherMock;

    private ThemeService $themeService;

    private Context $context;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $this->themeRepositoryMock = $this->createMock(EntityRepository::class);
        $this->themeSalesChannelRepositoryMock = $this->createMock(EntityRepository::class);
        $this->themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $databaseConfigLoaderMock = $this->createMock(DatabaseConfigLoader::class);
        $this->context = Context::createDefaultContext();

        $this->themeService = new ThemeService(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
            $this->themeSalesChannelRepositoryMock,
            $this->themeCompilerMock,
            $this->eventDispatcherMock,
            $databaseConfigLoaderMock,
            $this->connectionMock
        );
    }

    public function testAssignTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeSalesChannelRepositoryMock->expects(static::once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]],
            $this->context
        );

        $this->eventDispatcherMock->expects(static::once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::SALES_CHANNEL)
        );

        $this->themeCompilerMock->expects(static::once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $assigned = $this->themeService->assignTheme($themeId, TestDefaults::SALES_CHANNEL, $this->context);

        static::assertTrue($assigned);
    }

    public function testAssignThemeSkipCompile(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeSalesChannelRepositoryMock->expects(static::once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]],
            $this->context
        );

        $this->eventDispatcherMock->expects(static::once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::SALES_CHANNEL)
        );

        $this->themeCompilerMock->expects(static::never())->method('compileTheme');

        $assigned = $this->themeService->assignTheme($themeId, TestDefaults::SALES_CHANNEL, $this->context, true);

        static::assertTrue($assigned);
    }

    public function testCompileTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeCompilerMock->expects(static::once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeGivenConf(): void
    {
        $themeId = Uuid::randomHex();

        $confCollection = new StorefrontPluginConfigurationCollection();

        $this->themeCompilerMock->expects(static::once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            $confCollection,
            true,
            $this->context
        );

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context, $confCollection);
    }

    public function testCompileThemeWithAssets(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeCompilerMock->expects(static::once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            false,
            $this->context
        );

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context, null, false);
    }

    public function testCompileThemeById(): void
    {
        $themeId = Uuid::randomHex();
        $dependendThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependendThemeId,
                    'dsaleschannelId' => TestDefaults::SALES_CHANNEL,
                ],
            ]
        );

        $this->themeCompilerMock->expects(static::exactly(2))->method('compileTheme')->withConsecutive(
            [
                TestDefaults::SALES_CHANNEL,
                $themeId,
                static::anything(),
                static::anything(),
                true,
                $this->context,
            ],
            [
                TestDefaults::SALES_CHANNEL,
                $dependendThemeId,
                static::anything(),
                static::anything(),
                true,
                $this->context,
            ]
        );

        $mapping = $this->themeService->compileThemeById($themeId, $this->context);

        static::assertIsArray($mapping);
    }

    public function testUpdateThemeNoTheme(): void
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

        static::expectException(InvalidThemeException::class);
        static::expectExceptionMessage('Unable to find the theme "' . $themeId . '"');

        $this->themeService->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateTheme(): void
    {
        $themeId = Uuid::randomHex();
        $dependendThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependendThemeId,
                    'dsaleschannelId' => TestDefaults::SALES_CHANNEL,
                ],
            ]
        );

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection(
                    [
                        (new ThemeEntity())->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
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

        $this->themeCompilerMock->expects(static::exactly(2))->method('compileTheme');

        $this->themeService->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateThemeWithConfig(): void
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $dependendThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependendThemeId,
                    'dsaleschannelId' => TestDefaults::SALES_CHANNEL,
                ],
            ]
        );

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection(
                    [
                        (new ThemeEntity())->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                                'salesChannels' => new SalesChannelCollection(),
                                'configValues' => [
                                    'test' => ['value' => ['no_test']],
                                ],
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->eventDispatcherMock->expects(static::once())->method('dispatch')->with(
            new ThemeConfigChangedEvent($themeId, ['test' => ['value' => ['test']]])
        );

        $this->themeCompilerMock->expects(static::exactly(2))->method('compileTheme');

        $this->themeService->updateTheme($themeId, ['test' => ['value' => ['test']]], $parentThemeId, $this->context);
    }

    public function testUpdateThemeNoSalesChannelAssigned(): void
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
                                '_uniqueIdentifier' => $themeId,
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->themeCompilerMock->expects(static::never())->method('compileTheme');

        $this->themeService->updateTheme($themeId, null, null, $this->context);
    }

    public function testResetTheme(): void
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
                                '_uniqueIdentifier' => $themeId,
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->eventDispatcherMock->expects(static::once())->method('dispatch')->with(
            new ThemeConfigResetEvent($themeId)
        );

        $this->themeRepositoryMock->expects(static::once())->method('update')->with(
            [
                [
                    'id' => $themeId,
                    'configValues' => null,
                ],
            ],
            $this->context
        );

        $this->themeService->resetTheme($themeId, $this->context);
    }

    public function testResetThemeNoTheme(): void
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

        static::expectException(InvalidThemeException::class);
        static::expectExceptionMessage('Unable to find the theme "' . $themeId . '"');

        $this->themeService->resetTheme($themeId, $this->context);
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

        static::expectException(InvalidThemeException::class);
        static::expectExceptionMessage('Unable to find the theme "' . $themeId . '"');

        $this->themeService->getThemeConfiguration($themeId, false, $this->context);
    }

    /**
     * @dataProvider getThemeCollectionForThemeConfiguration
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     */
    public function testGetThemeConfiguration(array $ids, ThemeCollection $themeCollection, ?array $expected = null): void
    {
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

        $config = $this->themeService->getThemeConfiguration($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @dataProvider getThemeCollectionForThemeConfiguration
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     */
    public function testGetThemeConfigurationNoTranslation(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null
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

        $config = $this->themeService->getThemeConfiguration($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @dataProvider getThemeCollectionForThemeConfiguration
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     */
    public function testGetThemeConfigurationStructured(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null
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

        $config = $this->themeService->getThemeConfigurationStructuredFields($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @dataProvider getThemeCollectionForThemeConfiguration
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
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

        $config = $this->themeService->getThemeConfigurationStructuredFields($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @return array<int, array<string, array<string, array<int|string, mixed>|string>|ThemeCollection|null>>
     */
    public function getThemeCollectionForThemeConfiguration(): array
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
                                '_uniqueIdentifier' => $themeId,
                                'salesChannels' => new SalesChannelCollection(),
                                'configValues' => [
                                    'test' => ['value' => ['no_test']],
                                ],
                            ]
                        ),
                        (new ThemeEntity())->assign(
                            [
                                'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                                '_uniqueIdentifier' => Uuid::randomHex(),
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
                                '_uniqueIdentifier' => $themeId,
                                'salesChannels' => new SalesChannelCollection(),
                                'configValues' => [],
                            ]
                        ),
                        (new ThemeEntity())->assign(
                            [
                                'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                                '_uniqueIdentifier' => Uuid::randomHex(),
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
                                'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                                '_uniqueIdentifier' => Uuid::randomHex(),
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
        ];
    }
}
