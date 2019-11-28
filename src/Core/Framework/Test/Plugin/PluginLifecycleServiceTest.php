<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SwagTest\Migration\Migration1536761533Test;
use SwagTest\SwagTest;
use SwagTestWithoutConfig\SwagTestWithoutConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class PluginLifecycleServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const PLUGIN_NAME = 'SwagTest';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();

        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->getParameter('kernel.project_dir'),
            $this->container->get(PluginFinder::class)
        );
        $this->pluginCollection = $this->container->get(KernelPluginCollection::class);
        $this->connection = $this->container->get(Connection::class);
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();
        require_once __DIR__ . '/_fixture/plugins/SwagTest/src/Migration/Migration1536761533Test.php';
        $this->addTestPluginToKernel();
        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();
    }

    public function testInstallPlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());

        static::assertSame(1, $this->getMigrationTestKeyCount());

        static::assertSame(7, $this->systemConfigService->get('SwagTest.config.intField'));
        static::assertNull($this->systemConfigService->get('SwagTest.config.textFieldWithoutDefault'));
        static::assertSame('string', $this->systemConfigService->get('SwagTest.config.textField'));
        static::assertNull($this->systemConfigService->get('SwagTest.config.textFieldNull'));
        static::assertFalse($this->systemConfigService->get('SwagTest.config.switchField'));
        static::assertSame(0.349831239840912348, $this->systemConfigService->get('SwagTest.config.floatField'));
    }

    public function testInstallPluginWithoutConfig(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->pluginService->getPluginByName('SwagTestWithoutConfig', $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->pluginService->getPluginByName('SwagTestWithoutConfig', $this->context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $installedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_VERSION, $installedAt);

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertSame($installedAt, $pluginInstalled->getInstalledAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testInstallPluginWithUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_OLD_VERSION);
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertNotNull($pluginInstalled->getUpgradedAt());
        static::assertSame(SwagTest::PLUGIN_VERSION, $pluginInstalled->getVersion());
    }

    public function testUninstallPlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->uninstallPlugin($pluginInstalled, $this->context);

        $pluginUninstalled = $this->getTestPlugin();

        static::assertNull($pluginUninstalled->getInstalledAt());
        static::assertFalse($pluginUninstalled->getActive());
    }

    public function testUninstallPluginThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->uninstallPlugin($plugin, $this->context);
    }

    public function testUpdatePlugin(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_OLD_VERSION);
        static::assertSame(0, $this->getMigrationTestKeyCount());

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->updatePlugin($plugin, $this->context);

        $pluginUpdated = $this->getTestPlugin();

        static::assertNotNull($pluginUpdated->getUpgradedAt());
        static::assertSame(SwagTest::PLUGIN_VERSION, $pluginUpdated->getVersion());

        static::assertSame(1, $this->getMigrationTestKeyCount());
    }

    public function testActivatePlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $this->context);

        $pluginActivated = $this->getTestPlugin();

        static::assertTrue($pluginActivated->getActive());

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    public function testActivatePluginThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->activatePlugin($plugin, $this->context);
    }

    public function testDeactivatePlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $this->context);

        $pluginActivated = $this->getTestPlugin();

        static::assertTrue($pluginActivated->getActive());

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $this->context);

        $pluginDeactivated = $this->getTestPlugin();

        static::assertFalse($pluginDeactivated->getActive());

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    public function testDeactivatePluginNotInstalledThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($plugin, $this->context);
    }

    public function testDeactivatePluginNotActivatedThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $plugin = $this->getTestPlugin();

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        $pluginInstalled = $this->getTestPlugin();

        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->expectException(PluginNotActivatedException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not activated.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($pluginInstalled, $this->context);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollection::class),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(MigrationRuntime::class),
            $this->connection,
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->systemConfigService
        );
    }

    private function addTestPluginToKernel(): void
    {
        $testPluginBaseDir = __DIR__ . '/_fixture/plugins/SwagTest';
        $testPluginWithoutConfigBaseDir = __DIR__ . '/_fixture/plugins/SwagTestWithoutConfig';
        require_once $testPluginBaseDir . '/src/SwagTest.php';
        require_once $testPluginWithoutConfigBaseDir . '/src/SwagTestWithoutConfig.php';
        $this->pluginCollection->add(new SwagTest(false, $testPluginBaseDir));
        $this->pluginCollection->add(new SwagTestWithoutConfig(false, $testPluginWithoutConfigBaseDir));
    }

    private function getMigrationTestKeyCount(): int
    {
        $result = $this->connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1536761533Test::TEST_SYSTEM_CONFIG_KEY]
        );

        return (int) $result->fetchColumn();
    }

    private function getTestPlugin(): PluginEntity
    {
        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
    }
}
