<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use SwagExampleTest\SwagExampleTest;
use SwagInvalidTest\SwagInvalidTest;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @group skip-paratest
 */
class ConfigurationServiceInFlagTest extends TestCase
{
    use IntegrationTestBehaviour;

    public static $appEnvValue;

    public static $customCacheId = 'beef3f0ee9c61829627676afd6294bb029';

    private $fixtureFlags = [
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
    ];

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    private static $featureAllValue;

    public static function setUpBeforeClass(): void
    {
        self::$featureAllValue = $_SERVER['FEATURE_ALL'] ?? 'false';
        self::$appEnvValue = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'];
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    public static function tearDownAfterClass(): void
    {
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'] = self::$featureAllValue;
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = self::$appEnvValue;
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    protected function setUp(): void
    {
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'] = 'false';
        $_SERVER['APP_ENV'] = 'test';

        unset($_SERVER['FEATURE_NEXT_101']);
        unset($_SERVER['FEATURE_NEXT_102']);

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->getContainer()->getParameter('shopware.feature.flags'));

        $this->configurationService = $this->getConfigurationService();
    }

    protected function tearDown(): void
    {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = self::$appEnvValue;
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    public function testConfigurationFeatureFlag(): void
    {
        $this->setUpFixtures();

        $_SERVER['FEATURE_NEXT_101'] = '1';
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->configurationService->getConfiguration(
            SwagExampleTest::PLUGIN_NAME . '.card',
            Context::createDefaultContext()
        );

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $this->setUpFixtures();

        $actualConfig = $this->configurationService->getConfiguration(
            SwagExampleTest::PLUGIN_NAME . '.card',
            Context::createDefaultContext()
        );

        static::assertEmpty($actualConfig);
    }

    private function getConfigurationService(): ConfigurationService
    {
        return new ConfigurationService(
            $this->getTestPlugins(),
            new ConfigReader(),
            $this->getContainer()->get(AppLoader::class),
            $this->getContainer()->get('app.repository')
        );
    }

    /**
     * @return BundleInterface[]
     */
    private function getTestPlugins(): array
    {
        require_once __DIR__ . '/_fixtures/SwagExampleTest/SwagExampleTest.php';
        require_once __DIR__ . '/_fixtures/SwagInvalidTest/SwagInvalidTest.php';

        return [
            new SwagExampleTest(true, __DIR__ . '/_fixtures/SwagExampleTest'),
            new SwagInvalidTest(true, __DIR__ . '/_fixtures/SwagInvalidTest/SwagInvalidTest.php'),
        ];
    }

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
                        'name' => 'SwagExampleTest.card.email',
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
                    1 => [
                        'name' => 'SwagExampleTest.card.withoutAnyConfig',
                        'type' => 'int',
                        'config' => new \stdClass(),
                    ],
                    2 => [
                        'name' => 'SwagExampleTest.card.mailMethod',
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

    private function setUpFixtures(): void
    {
        //init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll());
        $registeredFlags = array_merge($registeredFlags, $this->fixtureFlags);

        Feature::registerFeatures($registeredFlags);
    }
}
