<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\BundleNotFoundException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var Context
     */
    private $context;

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
            \SwagInvalidTest\SwagInvalidTest::PLUGIN_NAME
        );
    }

    public function testGetConfigurationFromBundleWithoutExistingValues(): void
    {
        $actualConfig = $this->configurationService->getConfiguration(
            \SwagExampleTest\SwagExampleTest::PLUGIN_NAME
        );

        static::assertSame($this->getConfigWithoutValues(), $actualConfig);
    }

    protected function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            $kernel = new TestKernel('system_config_test', true, new ClassLoader());
            $kernel->boot();
            $this->container = $kernel->getContainer();
        }

        return $this->container;
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
                        'type' => 'text',
                        'name' => 'SwagExampleTest.email',
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
                        'type' => 'single-select',
                        'name' => 'SwagExampleTest.mailMethod',
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
        return new ConfigurationService($this->getContainer()->get('kernel'), new ConfigReader());
    }
}
