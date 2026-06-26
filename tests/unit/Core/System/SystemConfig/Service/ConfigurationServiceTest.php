<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $serverVarsBackup;

    /**
     * @var array<mixed>
     */
    private array $envVarsBackup;

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    protected function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testInvalidDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $appRepository = new StaticEntityRepository([]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        static::assertFalse($configService->checkSystemConfiguration('invalid!', Context::createDefaultContext()));

        $configService->getSystemConfiguration('invalid!', Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testInvalidDomain will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidDomainDeprecated(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $this->createMock(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        static::assertFalse($configService->checkConfiguration('invalid!', Context::createDefaultContext()));

        $configService->getConfiguration('invalid!', Context::createDefaultContext());
    }

    public function testMissingConfig(): void
    {
        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getSystemConfiguration('missing', Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testMissingConfig will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testMissingConfigDeprecated(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $this->createMock(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    public function testConfigurationFeatureFlag(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '1';
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testConfigurationFeatureFlag will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationFeatureFlagDeprecated(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '1';
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = Feature::silent('v6.8.0.0', fn () => $this->getConfigurationDeprecated($this->getAppConfig()));

        $expectedConfigWithoutValues = $this->getConfigWithoutValuesDeprecated();

        static::assertSame($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabled(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '0';
        $_SERVER['FEATURE_NEXT_102'] = '0';
        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('SYSTEM_CONFIG_TABS')) {
            unset($config[0]['cards'][0]['flag']); // make card not rely on feature flag (won't be removed)
            $config[0]['cards'][0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

            // create new card at position 0 and make it rely on feature flag (will be removed)
            array_unshift($config[0]['cards'], [
                'title' => [
                    'en-GB' => 'Advanced configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [],
                'flag' => 'FEATURE_NEXT_101',
            ]);
        } else {
            unset($config[0]['flag']); // make card not rely on feature flag (won't be removed)
            $config[0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

            // create new card at position 0 and make it rely on feature flag (will be removed)
            array_unshift($config, [
                'title' => [
                    'en-GB' => 'Advanced configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [],
                'flag' => 'FEATURE_NEXT_101',
            ]);
        }

        $actualConfig = $this->getConfiguration($config);

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertIsList($actualConfig[0]->cards[0]->elements);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabled will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabledDeprecated(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '0';
        $_SERVER['FEATURE_NEXT_102'] = '0';
        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

        if (Feature::isActive('SYSTEM_CONFIG_TABS')) {
            unset($config[0]['cards'][0]['flag']); // make card not rely on feature flag (won't be removed)
            $config[0]['cards'][0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

            // create new card at position 0 and make it rely on feature flag (will be removed)
            array_unshift($config[0]['cards'], [
                'title' => [
                    'en-GB' => 'Advanced configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [],
                'flag' => 'FEATURE_NEXT_101',
            ]);
        } else {
            unset($config[0]['flag']); // make card not rely on feature flag (won't be removed)
            $config[0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

            // create new card at position 0 and make it rely on feature flag (will be removed)
            array_unshift($config, [
                'title' => [
                    'en-GB' => 'Advanced configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [],
                'flag' => 'FEATURE_NEXT_101',
            ]);
        }

        $actualConfig = Feature::silent('v6.8.0.0', fn () => $this->getConfigurationDeprecated($config));

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]['elements']);
        static::assertCount(1, $actualConfig[0]['elements']);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertEmpty($actualConfig[0]->cards);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testConfigurationNoFeatureFlag will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationNoFeatureFlagDeprecated(): void
    {
        $actualConfig = $this->getConfigurationDeprecated($this->getAppConfig());

        static::assertEmpty($actualConfig);
    }

    public function testEmptyConfigThrowsError(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExampleTest'));

        $this->getConfiguration([]);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testEmptyConfigThrowsError will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEmptyConfigThrowsErrorDeprecated(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExampleTest'));

        $this->getConfigurationDeprecated([]);
    }

    public function testElementWithFlag(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
                    0 => [
                        'title' => [
                            'en-GB' => 'Basic configuration',
                            'de-DE' => 'Grundeinstellungen',
                        ],
                        'name' => null,
                        'elements' => [
                            [
                                'name' => 'SwagExampleTest.email',
                                'type' => 'text',
                                'flag' => 'FEATURE_NEXT_101',
                                'config' => [
                                    'copyable' => true,
                                    'label' => [
                                        'en-GB' => 'eMail',
                                        'de-DE' => 'E-Mail',
                                    ],
                                    'placeholder' => [
                                        'en-GB' => 'Enter your eMail address',
                                        'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertSame([], $actualConfig[0]->cards[0]->elements);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testElementWithFlag will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testElementWithFlagDeprecated(): void
    {
        $config = [
            0 => [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'SwagExampleTest.email',
                        'type' => 'text',
                        'flag' => 'FEATURE_NEXT_101',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'de-DE' => 'E-Mail',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (Feature::isActive('SYSTEM_CONFIG_TABS')) {
            $config = [
                [
                    'title' => null,
                    'name' => null,
                    'cards' => $config,
                ],
            ];
        }

        $actualConfig = $this->getConfigurationDeprecated($config);

        static::assertSame([], $actualConfig[0]['elements']);
    }

    public function testCacheRelevantMetadataIsExposedInElementConfig(): void
    {
       $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
                    0 => [
                        'title' => [
                            'en-GB' => 'Basic configuration',
                        ],
                        'name' => null,
                        'elements' => [
                            [
                                'name' => 'storefrontVisibility',
                                'type' => 'bool',
                                'cacheRelevant' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertTrue($actualConfig[0]->cards[0]->elements[0]->config['cacheRelevant']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testCacheRelevantMetadataIsExposedInElementConfig will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCacheRelevantMetadataIsExposedInElementConfigDeprecated(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'storefrontVisibility',
                        'type' => 'bool',
                        'cacheRelevant' => true,
                    ],
                ],
            ],
        ];

        if (Feature::isActive('SYSTEM_CONFIG_TABS')) {
            $config = [
                [
                    'title' => null,
                    'name' => null,
                    'cards' => $config,
                ],
            ];
        }

        $actualConfig = $this->getConfigurationDeprecated($config);

        static::assertTrue($actualConfig[0]['elements'][0]['config']['cacheRelevant']);
    }

    public function testConfigFromPlugin(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
                    [
                        'title' => [
                            'en-GB' => 'Basic configuration',
                            'de-DE' => 'Grundeinstellungen',
                        ],
                        'name' => null,
                        'elements' => [
                            [
                                'name' => 'email',
                                'type' => 'text',
                                'config' => [
                                    'copyable' => true,
                                    'label' => [
                                        'en-GB' => 'eMail',
                                        'de-DE' => 'E-Mail',
                                    ],
                                    'placeholder' => [
                                        'en-GB' => 'Enter your eMail address',
                                        'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            $this->createMock(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $actualConfig = $service->getSystemConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]->cards[0]->elements[0]->name);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testConfigFromPlugin will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigFromPluginDeprecated(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'de-DE' => 'E-Mail',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (Feature::isActive('SYSTEM_CONFIG_TABS')) {
            $config = [
                [
                    'title' => null,
                    'name' => null,
                    'cards' => $config,
                ],
            ];
        }

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $actualConfig = $service->getConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]['elements'][0]['name']);
    }

    public function testEnrichConfig(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
                    [
                        'title' => [
                            'en-GB' => 'Basic configuration',
                            'de-DE' => 'Grundeinstellungen',
                        ],
                        'elements' => [
                            [
                                'name' => 'email',
                                'type' => 'text',
                                'config' => [
                                    'copyable' => true,
                                    'label' => [
                                        'en-GB' => 'eMail',
                                        'de-DE' => 'E-Mail',
                                    ],
                                    'placeholder' => [
                                        'en-GB' => 'Enter your eMail address',
                                        'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new AppCollection()]);

        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            $this->createMock(AppConfigReader::class),
            $repository,
            new StaticSystemConfigService(['SwagExampleTest.email' => 'foo']),
            new NullLogger()
        );

        $actualConfig = $service->getResolvedSystemConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]->cards[0]->elements[0]->name);
        static::assertSame('foo', $actualConfig[0]->cards[0]->elements[0]->value);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testEnrichConfig will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnrichConfigDeprecated(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'elements' => [
                    [
                        'name' => 'email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'de-DE' => 'E-Mail',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (Feature::isActive('SYSTEM_CONFIG_TABS')) {
            $config = [
                [
                    'title' => null,
                    'name' => null,
                    'cards' => $config,
                ],
            ];
        }

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new AppCollection()]);

        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $repository,
            new StaticSystemConfigService(['SwagExampleTest.email' => 'foo']),
            new NullLogger()
        );

        $actualConfig = $service->getResolvedConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]['elements'][0]['name']);
        static::assertSame('foo', $actualConfig[0]['elements'][0]['value']);
    }

    public function testCheckConfigurationReturnsFalseOnXmlParsingException(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/path/to/config.xml', 'Invalid XML: element name contains underscores')
        );

        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [new SwagExampleTest(true, '')],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        // checkSystemConfiguration should return false instead of throwing the exception
        static::assertFalse($configService->checkSystemConfiguration('SwagExampleTest.config', Context::createDefaultContext()));
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testCheckConfigurationReturnsFalseOnXmlParsingException will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCheckConfigurationReturnsFalseOnXmlParsingExceptionDeprecated(): void
    {
        $configReader = $this->createMock(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/path/to/config.xml', 'Invalid XML: element name contains underscores')
        );

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [new SwagExampleTest(true, '')],
            $configReader,
            $this->createMock(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        // checkConfiguration should return false instead of throwing the exception
        static::assertFalse($configService->checkConfiguration('SwagExampleTest.config', Context::createDefaultContext()));
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<SystemConfigTab>
     */
    public function getConfiguration(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        $appConfigReader = static::createStub(AppConfigReader::class);
        $appConfigReader->method('read')->willReturnMap([[$app, $config]]);

        $appRepository = new StaticEntityRepository([
            new AppCollection([$app]),
            new AppCollection([$app]),
        ]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $appConfigReader,
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        if ($config !== []) {
            static::assertTrue($configService->checkSystemConfiguration('SwagExampleTest', Context::createDefaultContext()));
        }

        return $configService->getSystemConfiguration('SwagExampleTest', Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. getConfiguration is used as replacement instead
     *
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    public function getConfigurationDeprecated(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        $appConfigReader = $this->createMock(AppConfigReader::class);
        $appConfigReader->method('read')->with($app)->willReturn($config);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection([$app]),
            new AppCollection([$app]),
        ]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $appConfigReader,
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        if ($config !== []) {
            static::assertTrue($configService->checkConfiguration('SwagExampleTest', Context::createDefaultContext()));
        }

        return $configService->getConfiguration('SwagExampleTest', Context::createDefaultContext());
    }

    /**
     * @return list<SystemConfigTab>
     */
    private function getConfigWithoutValues(): array
    {
        return [
            0 => new SystemConfigTab(
                [
                    0 => new SystemConfigCard(
                        [
                            0 => new SystemConfigElement(
                                'SwagExampleTest.email',
                                [
                                    'copyable' => true,
                                    'label' => [
                                        'en-GB' => 'eMail',
                                        'de-DE' => 'E-Mail',
                                    ],
                                    'placeholder' => [
                                        'en-GB' => 'Enter your eMail address',
                                        'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                                    ],
                                ],
                                'text'
                            ),
                            1 => new SystemConfigElement(
                                'SwagExampleTest.withoutAnyConfig',
                                [],
                                'int'
                            ),
                            2 => new SystemConfigElement(
                                'SwagExampleTest.mailMethod',
                                [
                                    'options' => [
                                        0 => [
                                            'id' => 'smtp',
                                            'name' => [
                                                'en-GB' => 'SMTP',
                                            ],
                                        ],
                                        1 => [
                                            'id' => 'pop3',
                                            'name' => [
                                                'en-GB' => 'POP3',
                                            ],
                                        ],
                                    ],
                                    'label' => [
                                        'en-GB' => 'Mailing protocol',
                                        'de-DE' => 'E-Mail Versand Protokoll',
                                    ],
                                    'placeholder' => [
                                        'en-GB' => 'Choose your preferred transfer method',
                                        'de-DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                                    ],
                                    'flag' => 'FEATURE_NEXT_102',
                                ],
                                'single-select'
                            ),
                        ],
                        [
                            'en-GB' => 'Basic configuration',
                            'de-DE' => 'Grundeinstellungen',
                        ]
                    ),
                ]
            ),
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. getConfigWithoutValues is used as replacement instead
     *
     * @return array<mixed>
     */
    private function getConfigWithoutValuesDeprecated(): array
    {
        $config = [
            0 => [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    0 => [
                        'name' => 'SwagExampleTest.email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'de-DE' => 'E-Mail',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                            ],
                        ],
                    ],
                    [
                        'name' => 'SwagExampleTest.withoutAnyConfig',
                        'type' => 'int',
                        'config' => [],
                    ],
                    [
                        'name' => 'SwagExampleTest.mailMethod',
                        'type' => 'single-select',
                        'config' => [
                            'options' => [
                                0 => [
                                    'id' => 'smtp',
                                    'name' => [
                                        'en-GB' => 'SMTP',
                                    ],
                                ],
                                1 => [
                                    'id' => 'pop3',
                                    'name' => [
                                        'en-GB' => 'POP3',
                                    ],
                                ],
                            ],
                            'label' => [
                                'en-GB' => 'Mailing protocol',
                                'de-DE' => 'E-Mail Versand Protokoll',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Choose your preferred transfer method',
                                'de-DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                            ],
                            'flag' => 'FEATURE_NEXT_102',
                        ],
                    ],
                ],
                'flag' => 'FEATURE_NEXT_101',
            ],
        ];

        return $config;
    }

    /**
     * @return array<mixed>
     */
    private function getAppConfig(): array
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    [
                        'type' => 'text',
                        'name' => 'email',
                        'copyable' => true,
                        'label' => [
                            'en-GB' => 'eMail',
                            'de-DE' => 'E-Mail',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Enter your eMail address',
                            'de-DE' => 'Bitte gib deine E-Mail Adresse ein',
                        ],
                    ],
                    [
                        'type' => 'int',
                        'name' => 'withoutAnyConfig',
                    ],
                    [
                        'type' => 'single-select',
                        'name' => 'mailMethod',
                        'options' => [
                            [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'SMTP',
                                ],
                            ],
                            [
                                'id' => 'pop3',
                                'name' => [
                                    'en-GB' => 'POP3',
                                ],
                            ],
                        ],
                        'label' => [
                            'en-GB' => 'Mailing protocol',
                            'de-DE' => 'E-Mail Versand Protokoll',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Choose your preferred transfer method',
                            'de-DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                        ],
                        'flag' => 'FEATURE_NEXT_102',
                    ],
                ],
                'flag' => 'FEATURE_NEXT_101',
            ],
        ];

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('SYSTEM_CONFIG_TABS')) {
            return [
                [
                    'title' => null,
                    'name' => null,
                    'cards' => $config,
                ],
            ];
        }

        return $config;
    }
}

/**
 * @internal
 */
class SwagExampleTest extends Plugin
{
}
