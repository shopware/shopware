<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\SystemConfig\Exception\ConfigurationNotFoundException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Common\Stubs\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 *
 * @covers \Shopware\Core\System\SystemConfig\Service\ConfigurationService
 */
#[Package('system-settings')]
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

        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');
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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected domain');

        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $this->createMock(AppLoader::class),
            new StaticEntityRepository([]),
            new StaticSystemConfigService([])
        );

        static::assertFalse($configService->checkConfiguration('invalid!', Context::createDefaultContext()));

        $configService->getConfiguration('invalid!', Context::createDefaultContext());
    }

    public function testMissingConfig(): void
    {
        $this->expectException(ConfigurationNotFoundException::class);

        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $this->createMock(AppLoader::class),
            new StaticEntityRepository([new AppCollection()]),
            new StaticSystemConfigService([])
        );

        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    public function testConfigurationFeatureFlag(): void
    {
        $_SERVER['FEATURE_NEXT_101'] = '1';
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertEmpty($actualConfig);
    }

    public function testEmptyConfigThrowsError(): void
    {
        $this->expectException(ConfigurationNotFoundException::class);

        $this->getConfiguration([]);
    }

    public function testElementWithFlag(): void
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

        $actualConfig = $this->getConfiguration($config);
        static::assertSame([], $actualConfig[0]['elements']);
    }

    public function testConfigFromPlugin(): void
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

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            $this->createMock(AppLoader::class),
            new StaticEntityRepository([new AppCollection()]),
            new StaticSystemConfigService([])
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
            [
                'title' => [
                    'en-GB' => 'Foo',
                ],
            ],
        ];

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            $this->createMock(AppLoader::class),
            new StaticEntityRepository([new AppCollection()]),
            new StaticSystemConfigService(['SwagExampleTest.email' => 'foo'])
        );

        $actualConfig = $service->getResolvedConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(2, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]['elements'][0]['name']);
        static::assertSame('foo', $actualConfig[0]['elements'][0]['value']);
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    public function getConfiguration(array $config): array
    {
        $appLoader = $this->createMock(AppLoader::class);
        $appLoader->method('getConfiguration')->willReturn($config);

        $appCollection = new AppCollection([(new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test'])]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $appLoader,
            new StaticEntityRepository([
                $appCollection,
                $appCollection,
            ]),
            new StaticSystemConfigService([])
        );

        if ($config !== []) {
            static::assertTrue($configService->checkConfiguration('SwagExampleTest', Context::createDefaultContext()));
        }

        return $configService->getConfiguration('SwagExampleTest', Context::createDefaultContext());
    }

    /**
     * @return array<mixed>
     */
    private function getConfigWithoutValues(): array
    {
        return [
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
    }

    /**
     * @return array<mixed>
     */
    private function getAppConfig(): array
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
    }
}

/**
 * @internal
 */
class SwagExampleTest extends Plugin
{
}
