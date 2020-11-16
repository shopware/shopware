<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\ConfigurationNotFoundException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use SwagExampleTest\SwagExampleTest;
use SwagInvalidTest\SwagInvalidTest;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ConfigurationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    protected function setUp(): void
    {
        $this->configurationService = $this->getConfigurationService();
    }

    public function testThatWrongNamespaceThrowsException(): void
    {
        $this->expectException(ConfigurationNotFoundException::class);
        $this->configurationService->getConfiguration('InvalidNamespace', Context::createDefaultContext());
    }

    public function testThatBundleWithoutConfigThrowsException(): void
    {
        $this->expectException(BundleConfigNotFoundException::class);
        $this->configurationService->getConfiguration(
            SwagInvalidTest::PLUGIN_NAME . '.config',
            Context::createDefaultContext()
        );
    }

    public function testGetConfigurationFromBundleWithoutExistingValues(): void
    {
        $actualConfig = $this->configurationService->getConfiguration(
            SwagExampleTest::PLUGIN_NAME . '.config',
            Context::createDefaultContext()
        );

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testGetConfigurationFromApp(): void
    {
        $this->addApp('SwagAppConfig');

        $actualConfig = $this->configurationService->getConfiguration(
            'SwagAppConfig.config',
            Context::createDefaultContext()
        );

        $expectedConfig = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => 'TestCard',
                'elements' => [
                    [
                        'type' => 'text',
                        'name' => 'SwagAppConfig.config.email',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'de-DE' => 'E-Mail',
                            ],
                            'defaultValue' => 'no-reply@shopware.de',
                        ],
                    ],
                ],
            ],
        ];
        static::assertEquals($expectedConfig, $actualConfig);
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
                        'name' => 'SwagExampleTest.config.email',
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
                        'name' => 'SwagExampleTest.config.withoutAnyConfig',
                        'type' => 'int',
                        'config' => new \stdClass(),
                    ],
                    2 => [
                        'name' => 'SwagExampleTest.config.mailMethod',
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
                        ],
                    ],
                ],
            ],
        ];
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

    private function addApp(string $name): void
    {
        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/_fixtures/AppWithConfig');

        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'name' => $name,
            'path' => $path,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());
    }
}
