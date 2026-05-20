<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\Exception\ThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;

/**
 * @internal
 */
#[CoversClass(ThemeService::class)]
class ThemeServiceTest extends TestCase
{
    private Connection&MockObject $connectionMock;

    private StorefrontPluginRegistry&Stub $storefrontPluginRegistryMock;

    /**
     * @var EntityRepository<ThemeCollection>&MockObject
     */
    private EntityRepository&MockObject $themeRepositoryMock;

    /**
     * @var EntityRepository<EntityCollection<Entity>>&MockObject
     */
    private EntityRepository&MockObject $themeSalesChannelRepositoryMock;

    private ThemeCompiler&MockObject $themeCompilerMock;

    private EventDispatcher&MockObject $eventDispatcherMock;

    private ThemeMergedConfigBuilder&MockObject $mergedConfigBuilderMock;

    private DatabaseConfigLoader&MockObject $databaseConfigLoaderMock;

    private ThemeRuntimeConfigService&MockObject $runtimeConfigServiceMock;

    private ThemeService $themeService;

    private Context $context;

    private SystemConfigService&MockObject $systemConfigMock;

    private MessageBus&MockObject $messageBusMock;

    private ScssPhpCompiler&Stub $scssCompilerMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->storefrontPluginRegistryMock = static::createStub(StorefrontPluginRegistry::class);
        $this->themeRepositoryMock = $this->createMock(EntityRepository::class);
        $this->themeSalesChannelRepositoryMock = $this->createMock(EntityRepository::class);
        $this->themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->databaseConfigLoaderMock = $this->createMock(DatabaseConfigLoader::class);
        $this->context = Context::createDefaultContext();
        $this->systemConfigMock = $this->createMock(SystemConfigService::class);
        $this->messageBusMock = $this->createMock(MessageBus::class);
        $this->mergedConfigBuilderMock = $this->createMock(ThemeMergedConfigBuilder::class);
        $this->scssCompilerMock = static::createStub(ScssPhpCompiler::class);
        $this->runtimeConfigServiceMock = $this->createMock(ThemeRuntimeConfigService::class);

        $this->themeService = new ThemeService(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
            $this->themeSalesChannelRepositoryMock,
            $this->themeCompilerMock,
            $this->scssCompilerMock,
            $this->eventDispatcherMock,
            $this->databaseConfigLoaderMock,
            $this->connectionMock,
            $this->systemConfigMock,
            $this->messageBusMock,
            $this->createMock(NotificationService::class),
            $this->mergedConfigBuilderMock,
            $this->runtimeConfigServiceMock,
        );
    }

    public function testAssignTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->connectionMock->expects($this->once())->method('transactional')->willReturnCallback(static function (callable $callback): void {
            $callback();
        });

        $this->themeSalesChannelRepositoryMock->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]],
            $this->context
        );

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::SALES_CHANNEL, $this->context)
        );

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
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
        $this->connectionMock->expects($this->once())->method('transactional')->willReturnCallback(static function (callable $callback): void {
            $callback();
        });

        $themeId = Uuid::randomHex();

        $this->themeSalesChannelRepositoryMock->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]],
            $this->context
        );

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::SALES_CHANNEL, $this->context)
        );

        $this->themeCompilerMock->expects($this->never())->method('compileTheme');

        $assigned = $this->themeService->assignTheme($themeId, TestDefaults::SALES_CHANNEL, $this->context, true);

        static::assertTrue($assigned);
    }

    public function testCompileTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeAsyncSkipHeader(): void
    {
        $themeId = Uuid::randomHex();

        $this->context->addState(ThemeService::STATE_NO_QUEUE);

        $this->messageBusMock->expects($this->never())->method('dispatch');

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $this->systemConfigMock->method('get')->with(ThemeService::CONFIG_THEME_COMPILE_ASYNC)->willReturn(true);

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeAsyncSetting(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeCompilerMock->expects($this->never())->method('compileTheme');

        $context = $this->context;
        $this->messageBusMock->expects($this->once())->method('dispatch')
            ->willReturnCallback(static function () use ($themeId, $context): Envelope {
                return new Envelope(
                    new CompileThemeMessage(
                        TestDefaults::SALES_CHANNEL,
                        $themeId,
                        true,
                        $context
                    )
                );
            });

        $this->systemConfigMock->method('get')->with(ThemeService::CONFIG_THEME_COMPILE_ASYNC)->willReturn(true);

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeGivenConf(): void
    {
        $themeId = Uuid::randomHex();

        $confCollection = new StorefrontPluginConfigurationCollection();

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
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

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            false,
            $this->context
        );

        $this->themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context, null, false);
    }

    public function testRefreshThemeImportMap(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new StorefrontPluginConfiguration('Storefront');
        $configurationCollection = new StorefrontPluginConfigurationCollection();
        $importMap = ['imports' => ['shopware' => '/theme/shopware.js']];

        $this->databaseConfigLoaderMock
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $this->themeCompilerMock
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn($importMap);

        $this->runtimeConfigServiceMock
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                false,
                $configurationCollection,
                $importMap
            );

        $this->themeService->refreshThemeImportMap(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testCompileThemePassesEmptyImportMapWhenBuildReturnsNull(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new StorefrontPluginConfiguration('Storefront');
        $configurationCollection = new StorefrontPluginConfigurationCollection();

        $this->databaseConfigLoaderMock
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $this->themeCompilerMock
            ->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                $themeId,
                $storefrontConfig,
                $configurationCollection,
                true,
                $this->context
            );

        $this->themeCompilerMock
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn(null);

        $this->runtimeConfigServiceMock
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                true,
                $configurationCollection,
                ['imports' => []]
            );

        $this->themeService->compileTheme(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testRefreshThemeImportMapPassesEmptyImportMapWhenBuildReturnsNull(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new StorefrontPluginConfiguration('Storefront');
        $configurationCollection = new StorefrontPluginConfigurationCollection();

        $this->databaseConfigLoaderMock
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $this->themeCompilerMock
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn(null);

        $this->runtimeConfigServiceMock
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                false,
                $configurationCollection,
                ['imports' => []]
            );

        $this->themeService->refreshThemeImportMap(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testRefreshThemeImportMapReturnsEarlyWithStaticFileConfigLoader(): void
    {
        $themeId = Uuid::randomHex();
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(\sprintf('theme-config/%s.json', $themeId), json_encode([
            'styleFiles' => [],
            'scriptFiles' => [],
        ], \JSON_THROW_ON_ERROR));
        $configLoader = new StaticFileConfigLoader($fs);

        $themeService = new ThemeService(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
            $this->themeSalesChannelRepositoryMock,
            $this->themeCompilerMock,
            $this->scssCompilerMock,
            $this->eventDispatcherMock,
            $configLoader,
            $this->connectionMock,
            $this->systemConfigMock,
            $this->messageBusMock,
            $this->createMock(NotificationService::class),
            $this->mergedConfigBuilderMock,
            $this->runtimeConfigServiceMock,
        );

        $this->themeCompilerMock->expects($this->never())->method('buildComponentImportMap');
        $this->runtimeConfigServiceMock->expects($this->never())->method('refreshRuntimeConfig');

        $themeService->refreshThemeImportMap(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            $this->context,
            new StorefrontPluginConfigurationCollection()
        );
    }

    public function testCompileThemeById(): void
    {
        $themeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependentThemeId,
                    'dsaleschannelId' => TestDefaults::SALES_CHANNEL,
                ],
            ]
        );

        $parameters = [];

        $this->themeCompilerMock
            ->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(static function ($salesChannelId, $themeId) use (&$parameters): void {
                $parameters[] = [$salesChannelId, $themeId];
            });

        $this->themeService->compileThemeById($themeId, $this->context);

        static::assertSame([
            [
                TestDefaults::SALES_CHANNEL,
                $themeId,
            ],
            [
                TestDefaults::SALES_CHANNEL,
                $dependentThemeId,
            ],
        ], $parameters);
    }

    public function testUpdateTheme(): void
    {
        $themeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependentThemeId,
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

        // Mock the getPlainThemeConfiguration method to return an empty configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context)
            ->willReturn([
                'fields' => [],
            ]);

        $this->themeCompilerMock->expects($this->exactly(2))->method('compileTheme');

        $this->themeService->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateThemeWithConfig(): void
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependentThemeId,
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
                                'baseConfig' => [
                                    'fields' => [
                                        'test' => [
                                            'type' => 'string',
                                            'value' => 'test',
                                        ],
                                    ],
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

        // Mock the getPlainThemeConfiguration method to return the expected configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context)
            ->willReturn([
                'fields' => [
                    'test' => [
                        'type' => 'string',
                        'value' => 'test',
                    ],
                ],
            ]);

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            new ThemeConfigChangedEvent($themeId, ['test' => ['value' => ['test']]], $this->context)
        );

        $this->themeCompilerMock->expects($this->exactly(2))->method('compileTheme');

        $this->themeService->updateTheme($themeId, ['test' => ['value' => ['test']]], $parentThemeId, $this->context);
    }

    public function testUpdateThemeWithConfigAndRemovedField(): void
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'saleschannelId' => TestDefaults::SALES_CHANNEL,
                    'dependentId' => $dependentThemeId,
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
                                    'removed' => ['value' => ['still_here']],
                                ],
                                'baseConfig' => [
                                    'fields' => [
                                        'test' => [
                                            'type' => 'string',
                                            'value' => 'test',
                                        ],
                                    ],
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

        $config = [
            'test' => ['value' => ['test']],
            'removed' => ['value' => ['removed']],
        ];

        // Mock the getPlainThemeConfiguration method to return the expected configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context)
            ->willReturn([
                'fields' => [
                    'test' => [
                        'type' => 'string',
                        'value' => 'test',
                    ],
                ],
            ]);

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            new ThemeConfigChangedEvent($themeId, ['test' => ['value' => ['test']]], $this->context)
        );

        $this->themeCompilerMock->expects($this->exactly(2))->method('compileTheme');

        $this->themeService->updateTheme($themeId, $config, $parentThemeId, $this->context);
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

        // Mock the getPlainThemeConfiguration method to return an empty configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context)
            ->willReturn([
                'fields' => [],
            ]);

        $this->themeCompilerMock->expects($this->never())->method('compileTheme');

        $this->themeService->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateThemeNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection([]),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage(\sprintf('Could not find theme with id "%s"', $themeId));

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

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')->with(
            new ThemeConfigResetEvent($themeId, $this->context)
        );

        $this->themeRepositoryMock->expects($this->once())->method('update')->with(
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
                new ThemeCollection([]),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage(\sprintf('Could not find theme with id "%s"', $themeId));
        $this->themeService->resetTheme($themeId, $this->context);
    }

    public function testAsyncCompilationIsSkippedWhenUsingStaticConfigLoader(): void
    {
        $themeId = Uuid::randomHex();
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(\sprintf('theme-config/%s.json', $themeId), json_encode([
            'styleFiles' => [],
            'scriptFiles' => [],
        ], \JSON_THROW_ON_ERROR));
        $configLoader = new StaticFileConfigLoader($fs);

        $themeService = new ThemeService(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
            $this->themeSalesChannelRepositoryMock,
            $this->themeCompilerMock,
            $this->scssCompilerMock,
            $this->eventDispatcherMock,
            $configLoader,
            $this->connectionMock,
            $this->systemConfigMock,
            $this->messageBusMock,
            $this->createMock(NotificationService::class),
            $this->mergedConfigBuilderMock,
            $this->createMock(ThemeRuntimeConfigService::class),
        );

        $this->systemConfigMock->expects($this->never())->method('get');
        $this->messageBusMock->expects($this->never())->method('dispatch');

        $this->themeCompilerMock->expects($this->once())->method('compileTheme')->with(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $themeService->compileTheme(TestDefaults::SALES_CHANNEL, $themeId, $this->context);
    }

    public function testValidateThemeConfig(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-color-brand-primary' => [
                'value' => '#ff0000',
            ],
            'sw-non-scss-field' => [
                'value' => '#invalid',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
                'sw-ignore-field' => [
                    'name' => 'sw-ignore-field',
                    'type' => 'color',
                    'editable' => false,
                    'scss' => true,
                ],
                'sw-non-scss-field' => [
                    'name' => 'sw-non-scss-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => false,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')->willReturn('body{background-color:#ff0000;color:darken(#ff0000, 10%)}');

        $result = $this->themeService->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigWithInvalidValues(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-color-brand-primary' => [
                'value' => '#invalid-color',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        // Configure the mock to throw an exception when compileString is called
        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $this->expectException(ThemeConfigException::class);

        $this->themeService->validateThemeConfig($themeId, $config, $this->context);
    }

    public function testValidateThemeConfigWithSanitize(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-color-brand-primary' => [
                'value' => '#invalid-color',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $result = $this->themeService->validateThemeConfig($themeId, $config, $this->context, [], true);

        static::assertEquals([
            'sw-color-brand-primary' => [
                'value' => '#ffffff00',
            ],
        ], $result);
    }

    public function testValidateThemeConfigSkipsNonEditableFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-non-editable-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-non-editable-field' => [
                    'name' => 'sw-non-editable-field',
                    'type' => 'color',
                    'editable' => false,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $result = $this->themeService->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigSkipsNonScssFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-non-scss-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-non-scss-field' => [
                    'name' => 'sw-non-scss-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => false,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $result = $this->themeService->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigSkipsNonExistentFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'sw-non-existent-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'sw-existing-field' => [
                    'name' => 'sw-existing-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $result = $this->themeService->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testGetPlainThemeConfiguration(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['key' => 'value'];

        $this->mergedConfigBuilderMock
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context)
            ->willReturn($expectedConfig);

        $result = $this->themeService->getPlainThemeConfiguration($themeId, $this->context);

        static::assertSame($expectedConfig, $result);
    }

    /**
     * @deprecated tag:v6.8.0 will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetPlainThemeConfigurationWithTranslationFlag(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['key' => 'value'];

        $this->mergedConfigBuilderMock
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $this->context, true)
            ->willReturn($expectedConfig);

        $result = $this->themeService->getPlainThemeConfiguration($themeId, $this->context, true);

        static::assertSame($expectedConfig, $result);
    }

    public function testGetThemeConfigurationFieldStructure(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['structuredKey' => 'structuredValue'];

        $this->mergedConfigBuilderMock
            ->method('getThemeConfigurationFieldStructure')
            ->with($themeId, $this->context)
            ->willReturn($expectedConfig);

        $result = $this->themeService->getThemeConfigurationFieldStructure($themeId, $this->context);

        static::assertSame($expectedConfig, $result);
    }

    /**
     * @deprecated tag:v6.8.0 will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetThemeConfigurationFieldStructureWithTranslationFlag(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['structuredKey' => 'structuredValue'];

        $this->mergedConfigBuilderMock
            ->method('getThemeConfigurationFieldStructure')
            ->with($themeId, $this->context, true)
            ->willReturn($expectedConfig);

        $result = $this->themeService->getThemeConfigurationFieldStructure($themeId, $this->context, true);

        static::assertSame($expectedConfig, $result);
    }
}
