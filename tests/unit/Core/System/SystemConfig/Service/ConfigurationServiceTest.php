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
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\System;
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
use Shopware\Tests\Unit\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin\BrokenConfigPlugin;
use Shopware\Tests\Unit\Core\System\SystemConfig\Service\_fixtures\SwagExample\SwagExample;
use Shopware\Tests\Unit\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin\ValidConfigPlugin;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    use EnvTestBehaviour;

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidDomainDeprecated(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            new StaticEntityRepository([]),
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $configService->getConfiguration('invalid!', Context::createDefaultContext());
    }

    public function testInvalidDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            new StaticEntityRepository([]),
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $configService->getSystemConfigDefinition('invalid!', Context::createDefaultContext());
    }

    public function testCheckConfigurationWithInvalidDomain(): void
    {
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            new StaticEntityRepository([]),
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        static::assertFalse($configService->checkConfiguration('invalid!', Context::createDefaultContext()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testMissingConfigDeprecated(): void
    {
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            new StaticEntityRepository([new AppCollection([])]),
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    public function testMissingConfig(): void
    {
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            new StaticEntityRepository([new AppCollection([])]),
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getSystemConfigDefinition('missing', Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationFeatureFlagDeprecated(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '1',
            'FEATURE_NEXT_102' => '1',
        ]);

        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getLegacyConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertEquals($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertEquals($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testConfigurationFeatureFlag(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '1',
            'FEATURE_NEXT_102' => '1',
        ]);

        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getSystemConfigDefinition($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabledDeprecated(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '0',
            'FEATURE_NEXT_102' => '0',
        ]);

        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

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

        $actualConfig = $this->getConfiguration($config);

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]['elements']);
        static::assertCount(1, $actualConfig[0]['elements']);
    }

    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabled(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '0',
            'FEATURE_NEXT_102' => '0',
        ]);

        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

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

        $actualConfig = $this->getSystemConfigDefinition($config);

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertIsList($actualConfig[0]->cards[0]->elements);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationNoFeatureFlagDeprecated(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertEmpty($actualConfig);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getSystemConfigDefinition($this->getAppConfig());

        static::assertSame([], $actualConfig[0]->cards);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEmptyConfigThrowsErrorDeprecated(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExample'));

        $this->getConfiguration([]);
    }

    public function testEmptyConfigThrowsError(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExample'));

        $this->getSystemConfigDefinition([]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testElementWithFlagDeprecated(): void
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
                                'name' => 'SwagExample.email',
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

        static::assertSame([], $actualConfig[0]['elements']);
    }

    public function testElementWithFlag(): void
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
                                'name' => 'SwagExample.email',
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

        $actualConfig = $this->getSystemConfigDefinition($config);

        static::assertSame([], $actualConfig[0]->cards[0]->elements);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCacheRelevantMetadataIsExposedInElementConfigDeprecated(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
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
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertTrue($actualConfig[0]['elements'][0]['config']['cacheRelevant']);
    }

    public function testCacheRelevantMetadataIsExposedInElementConfig(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
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
                ],
            ],
        ];

        $actualConfig = $this->getSystemConfigDefinition($config);

        static::assertTrue($actualConfig[0]->cards[0]->elements[0]->config['cacheRelevant']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigFromPluginDeprecated(): void
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

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $systemConfigService = new StaticSystemConfigService([]);
        $service = new ConfigurationService(
            [
                new SwagExample(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );

        $actualConfig = $service->getConfiguration('SwagExample', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExample.email', $actualConfig[0]['elements'][0]['name']);
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

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $service = new ConfigurationService(
            [
                new SwagExample(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $actualConfig = $service->getSystemConfigDefinition('SwagExample', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExample.email', $actualConfig[0]->cards[0]->elements[0]->name);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnrichConfigDeprecated(): void
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

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $repository = new StaticEntityRepository([new AppCollection()]);

        $systemConfigService = new StaticSystemConfigService(['SwagExample.email' => 'foo']);
        $service = new ConfigurationService(
            [
                new SwagExample(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $repository,
            $systemConfigService,
            new NullLogger()
        );

        $actualConfig = $service->getResolvedConfiguration('SwagExample', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExample.email', $actualConfig[0]['elements'][0]['name']);
        static::assertSame('foo', $actualConfig[0]['elements'][0]['value']);
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

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $repository = new StaticEntityRepository([new AppCollection()]);

        $service = new ConfigurationService(
            [
                new SwagExample(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $repository,
            new StaticSystemConfigService(['SwagExample.email' => 'foo']),
            new NullLogger()
        );

        $actualConfig = $service->getResolvedSystemConfigDefinition('SwagExample', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExample.email', $actualConfig[0]->cards[0]->elements[0]->name);
        static::assertSame('foo', $actualConfig[0]->cards[0]->elements[0]->value);
    }

    public function testCheckConfigurationReturnsFalseForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // Should return false instead of throwing UtilXmlParsingException
        static::assertFalse(
            $configurationService->checkConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testCheckConfigurationReturnsTrueForValidConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new ValidConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/ValidConfigPlugin'),
        ]);

        static::assertTrue(
            $configurationService->checkConfiguration('ValidConfigPlugin.config', Context::createDefaultContext())
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetConfigurationThrowsExceptionForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getConfiguration should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $configurationService->getConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext());
    }

    public function testGetSystemConfigDefinitionThrowsExceptionForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getSystemConfigDefinition should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $configurationService->getSystemConfigDefinition('BrokenConfigPlugin.config', Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetResolvedConfigurationReturnsEmptyArrayForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedConfiguration uses checkConfiguration, so it should return empty array
        $result = $configurationService->getResolvedConfiguration(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testGetResolvedSystemConfigDefinitionReturnsEmptyArrayForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(active: true, basePath: __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedSystemConfigDefinition uses checkConfiguration, so it should return empty array
        $result = $configurationService->getResolvedSystemConfigDefinition(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testBasicInformationContainsCompanyInformationCardWhenFeatureFlagIsActive(): void
    {
        static::assertTrue(Feature::isActive('DOCUMENT_GENERATION_REWORK'));

        $configuration = $this->createConfigurationService([])->getSystemConfigDefinition(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SystemConfigTab::class, $configuration[0]);
        static::assertCount(1, array_filter(
            $configuration[0]->cards,
            static fn (SystemConfigCard $card): bool => $card->name === 'companyInformation'
        ));
    }

    #[DisabledFeatures(['DOCUMENT_GENERATION_REWORK'])]
    public function testBasicInformationDoesNotContainCompanyInformationCardWhenFeatureFlagIsInactive(): void
    {
        static::assertFalse(Feature::isActive('DOCUMENT_GENERATION_REWORK'));

        $configuration = $this->createConfigurationService([])->getSystemConfigDefinition(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SystemConfigTab::class, $configuration[0]);
        static::assertCount(0, array_filter(
            $configuration[0]->cards,
            static fn (SystemConfigCard $card): bool => $card->name === 'companyInformation'
        ));
    }

    public function testBasicInformationOffersASellerCountrySelectDirectlyAboveTheShopOwnerAddress(): void
    {
        $configuration = $this->createConfigurationService([])->getSystemConfigDefinition(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SystemConfigTab::class, $configuration[0]);
        $elements = $configuration[0]->cards[0]->elements;
        $position = array_search(
            'core.basicInformation.sellerCountryId',
            array_map(static fn (SystemConfigElement $element): string => $element->name, $elements),
            true
        );

        static::assertIsInt($position);
        static::assertSame('sw-entity-single-select', $elements[$position]->config['componentName']);
        static::assertSame('country', $elements[$position]->config['entity']);
        static::assertSame('core.basicInformation.address', ($elements[$position + 1] ?? null)?->name);
    }

    /**
     * @param list<Plugin> $plugins
     */
    private function createConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            [
                new System(),
                ...$plugins,
            ],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            StaticEntityRepository::of(AppCollection::class, []),
            new StaticSystemConfigService([]),
            new NullLogger()
        );
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function getConfiguration(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExample', '_uniqueIdentifier' => 'test']);

        $appConfigReader = static::createStub(AppConfigReader::class);
        $appConfigReader->method('read')->willReturnMap([[$app, $config]]);

        $appRepository = new StaticEntityRepository([
            new AppCollection([$app]),
            new AppCollection([$app]),
        ]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $appConfigReader,
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );

        if ($config !== []) {
            static::assertTrue($configService->checkConfiguration('SwagExample', Context::createDefaultContext()));
        }

        return $configService->getConfiguration('SwagExample', Context::createDefaultContext());
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<SystemConfigTab>
     */
    private function getSystemConfigDefinition(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExample', '_uniqueIdentifier' => 'test']);

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
            static::assertTrue($configService->checkConfiguration('SwagExample', Context::createDefaultContext()));
        }

        return $configService->getSystemConfigDefinition('SwagExample', Context::createDefaultContext());
    }

    /**
     * @return array<mixed>
     */
    private function getLegacyConfigWithoutValues(): array
    {
        return [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'SwagExample.email',
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
                        'value' => null,
                    ],
                    [
                        'name' => 'SwagExample.withoutAnyConfig',
                        'type' => 'int',
                        'config' => [],
                        'value' => null,
                    ],
                    [
                        'name' => 'SwagExample.mailMethod',
                        'type' => 'single-select',
                        'config' => [
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
                        'value' => null,
                    ],
                ],
                'subtitle' => null,
                'flag' => 'FEATURE_NEXT_101',
            ],
        ];
    }

    /**
     * @return list<SystemConfigTab>
     */
    private function getConfigWithoutValues(): array
    {
        return [
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [
                            new SystemConfigElement(
                                'SwagExample.email',
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
                            new SystemConfigElement(
                                'SwagExample.withoutAnyConfig',
                                [],
                                'int'
                            ),
                            new SystemConfigElement(
                                'SwagExample.mailMethod',
                                [
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
                                'single-select'
                            ),
                        ],
                        [
                            'en-GB' => 'Basic configuration',
                            'de-DE' => 'Grundeinstellungen',
                        ],
                        null,
                        null,
                        'FEATURE_NEXT_101',
                    ),
                ]
            ),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getAppConfig(): array
    {
        return [
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
                ],
            ],
        ];
    }
}
