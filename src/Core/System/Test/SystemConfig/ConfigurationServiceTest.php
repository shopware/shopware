<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\BundleNotFoundException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use SwagExampleTest\SwagExampleTest;
use SwagInvalidTest\SwagInvalidTest;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ConfigurationServiceTest extends TestCase
{
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
        $this->expectException(BundleNotFoundException::class);
        $this->configurationService->getConfiguration('InvalidNamespace');
    }

    public function testThatBundleWithoutConfigThrowsException(): void
    {
        $this->expectException(BundleConfigNotFoundException::class);
        $this->configurationService->getConfiguration(
            SwagInvalidTest::PLUGIN_NAME
        );
    }

    public function testGetConfigurationFromBundleWithoutExistingValues(): void
    {
        $actualConfig = $this->configurationService->getConfiguration(
            SwagExampleTest::PLUGIN_NAME
        );

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
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
                    1 => [
                        'name' => 'SwagExampleTest.withoutAnyConfig',
                        'type' => 'int',
                        'config' => new \stdClass(),
                    ],
                    2 => [
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
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getConfigurationService(): ConfigurationService
    {
        return new ConfigurationService($this->getTestPlugins(), new ConfigReader());
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
}
