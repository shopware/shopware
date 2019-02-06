<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\BundleNotFoundException;
use Shopware\Core\System\SystemConfig\Helper\ConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationServiceTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('system_config_test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
        $this->systemConfigRepository = $this->container->get('system_config.repository');
        $this->connection = $this->container->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->configurationService = $this->getConfigurationService();
    }

    protected function tearDown()
    {
        $ids = $this->systemConfigRepository->searchIds(new Criteria(), $this->context)->getIds();

        if ($ids) {
            $deleteData = [];

            foreach ($ids as $id) {
                $deleteData[] = ['id' => $id];
            }

            $this->systemConfigRepository->delete($deleteData, $this->context);
        }
    }

    public function testThatWrongNamespaceThrowsException(): void
    {
        $this->expectException(BundleNotFoundException::class);
        $this->configurationService->getConfiguration('InvalidNamespace', $this->context, '20080911ffff4fffafffffff19830531');
    }

    public function testThatBundleWithoutConfigThrowsException(): void
    {
        $this->expectException(BundleConfigNotFoundException::class);
        $this->configurationService->getConfiguration(\SwagInvalidTest\SwagInvalidTest::PLUGIN_NAME, $this->context, '20080911ffff4fffafffffff19830531');
    }

    public function testGetConfigurationFromBundleWithoutExistingValues(): void
    {
        $actualConfig = $this->configurationService->getConfiguration(\SwagExampleTest\SwagExampleTest::PLUGIN_NAME, $this->context, '20080911ffff4fffafffffff19830531');

        self::assertSame($this->getConfigWithoutValues(), $actualConfig);
    }

    public function testPatchValuesIntoConfig(): void
    {
        $this->initializeRepo();

        $method = ReflectionHelper::getMethod(ConfigurationService::class, 'patchValuesIntoConfig');

        $actualConfig = $method->invoke($this->configurationService,
            $this->getConfigWithoutValues(),
            \SwagExampleTest\SwagExampleTest::PLUGIN_NAME,
            '20080911ffff4fffafffffff19830531',
            $this->context
        );

        foreach ($actualConfig as $card) {
            foreach ($card['fields'] as $field) {
                self::assertNotNull($field['value']);

                if ($field['name'] === 'email') {
                    self::assertSame('test@example.com', $field['value']);
                }

                if ($field['name'] === 'mailMethod') {
                    self::assertSame('smtp', $field['value']);
                }
            }
        }
    }

    private function getConfigWithoutValues(): array
    {
        return [
            0 => [
                'title' => [
                    'en_GB' => 'Basic configuration',
                    'de_DE' => 'Grundeinstellungen',
                ],
                'fields' => [
                    0 => [
                        'type' => 'text',
                        'name' => 'email',
                        'copyable' => true,
                        'label' => [
                            'en_GB' => 'eMail',
                            'de_DE' => 'E-Mail',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Enter your eMail address',
                            'de_DE' => 'Bitte gib deine E-Mail Adresse ein',
                        ],
                        'value' => null,
                    ],
                    1 => [
                        'type' => 'select',
                        'name' => 'mailMethod',
                        'options' => [
                            0 => [
                                'value' => 'smtp',
                                'label' => [
                                    'en_GB' => 'SMTP',
                                ],
                            ],
                            1 => [
                                'value' => 'pop3',
                                'label' => [
                                    'en_GB' => 'POP3',
                                ],
                            ],
                        ],
                        'label' => [
                            'en_GB' => 'Mailing protocol',
                            'de_DE' => 'E-Mail Versand Protokoll',
                        ],
                        'placeholder' => [
                            'en_GB' => 'Choose your preferred transfer method',
                            'de_DE' => 'Bitte wähle dein bevorzugtes Versand Protokoll',
                        ],
                        'value' => null,
                    ],
                ],
            ],
        ];
    }

    private function initializeRepo(): void
    {
        $this->systemConfigRepository->upsert([
            [
                'namespace' => \SwagExampleTest\SwagExampleTest::PLUGIN_NAME,
                'configurationKey' => 'email',
                'configurationValue' => 'test@example.com',
                'salesChannelId' => '20080911ffff4fffafffffff19830531',
            ],
            [
                'namespace' => \SwagExampleTest\SwagExampleTest::PLUGIN_NAME,
                'configurationKey' => 'mailMethod',
                'configurationValue' => 'smtp',
                'salesChannelId' => '20080911ffff4fffafffffff19830531',
            ],
        ], $this->context);
    }

    private function getConfigurationService(): ConfigurationService
    {
        return new ConfigurationService($this->systemConfigRepository, $this->container->get('kernel'), new ConfigReader());
    }
}
