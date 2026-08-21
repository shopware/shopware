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
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(SystemConfigDefinitionService::class)]
class SystemConfigDefinitionServiceTest extends TestCase
{
    use EnvTestBehaviour;

    public function testInvalidDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $appRepository = new StaticEntityRepository([]);
        $configService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
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
        $configService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    public function testConfigurationFeatureFlag(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '1',
            'FEATURE_NEXT_102' => '1',
        ]);

        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
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

        $actualConfig = $this->getConfiguration($config);

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertIsList($actualConfig[0]->cards[0]->elements);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertSame([], $actualConfig[0]->cards);
    }

    public function testEmptyConfigThrowsError(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExampleTest'));

        $this->getConfiguration([]);
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

        $actualConfig = $this->getConfiguration($config);

        static::assertTrue($actualConfig[0]->cards[0]->elements[0]->config['cacheRelevant']);
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
        $service = new SystemConfigDefinitionService(
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
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]->cards[0]->elements[0]->name);
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

        $service = new SystemConfigDefinitionService(
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
        static::assertCount(1, $actualConfig[0]->cards);
        static::assertCount(1, $actualConfig[0]->cards[0]->elements);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]->cards[0]->elements[0]->name);
        static::assertSame('foo', $actualConfig[0]->cards[0]->elements[0]->value);
    }

    public function testCheckConfigurationReturnsFalseOnXmlParsingException(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/path/to/config.xml', 'Invalid XML: element name contains underscores')
        );

        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new SystemConfigDefinitionService(
            [new SwagExampleTest(true, '')],
            $configReader,
            static::createStub(AppConfigReader::class),
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
        $configService = new SystemConfigDefinitionService(
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
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [
                            new SystemConfigElement(
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
                            new SystemConfigElement(
                                'SwagExampleTest.withoutAnyConfig',
                                [],
                                'int'
                            ),
                            new SystemConfigElement(
                                'SwagExampleTest.mailMethod',
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
                        ]
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

/**
 * @internal
 */
class SwagExampleTest extends Plugin
{
}
