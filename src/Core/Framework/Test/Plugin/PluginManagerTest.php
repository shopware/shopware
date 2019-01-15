<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\Helper\ComposerPackageProvider;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManager;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManagerTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    private const PLUGIN_NAME = 'SwagTest';

    private const PLUGIN_LABEL = 'English plugin name';

    private const PLUGIN_VERSION = '1.0.1.0';

    private const PLUGIN_OLD_VERSION = '1.0.0';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->kernel = $this->getContainer()->get('kernel');
        $this->connection = $this->getContainer()->get(Connection::class);
        require_once __DIR__ . '/_fixture/SwagTest/Migration/Migration1536761533Test.php';
    }

    protected function tearDown(): void
    {
        $this->connection->executeUpdate(
            sprintf(
                'DROP TABLE IF EXISTS `%s`',
                \SwagTest\Migration\Migration1536761533Test::TABLE_NAME
            )
        );
        $this->connection->executeUpdate(
            sprintf(
                'DELETE FROM `migration` WHERE `creation_timestamp` = %d',
                \SwagTest\Migration\Migration1536761533Test::TIMESTAMP
            )
        );
        $this->connection->executeUpdate(
            sprintf(
                "DELETE FROM `plugin` WHERE `name` = '%s'",
                self::PLUGIN_NAME
            )
        );
    }

    public function testRefreshPlugins(): void
    {
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();

        $pluginManager->refreshPlugins($context, new NullIO());
        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $context)->first();

        $this->performDefaultTests($plugin);
        self::assertNull($plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsExistingWithPluginUpdate(): void
    {
        $context = Context::createDefaultContext();
        $this->createPlugin($context, self::PLUGIN_OLD_VERSION);

        $pluginManager = $this->createPluginManager();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $context)->first();

        self::assertSame(self::PLUGIN_NAME, $plugin->getName());
        self::assertSame(self::PLUGIN_LABEL, $plugin->getLabel());
        self::assertSame(self::PLUGIN_VERSION, $plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsExistingWithoutPluginUpdate(): void
    {
        $context = Context::createDefaultContext();
        $this->createPlugin($context);

        $pluginManager = $this->createPluginManager();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search(new Criteria(), $context)->first();

        $this->performDefaultTests($plugin);
        self::assertNull($plugin->getUpgradeVersion());
    }

    public function testRefreshPluginsDeleteNonExistingPlugin(): void
    {
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();

        $this->pluginRepo->create(
            [
                [
                    'name' => 'SwagFoo',
                    'version' => '1.1.1',
                    'label' => 'Foo Label',
                ],
            ],
            $context
        );

        $pluginManager->refreshPlugins($context, new NullIO());
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $context)->getEntities();
        self::assertCount(1, $pluginCollection);
        /** @var PluginEntity $plugin */
        $plugin = $pluginCollection->first();

        $this->performDefaultTests($plugin);
        self::assertNull($plugin->getUpgradeVersion());
    }

    public function testGetPluginByName(): void
    {
        $context = Context::createDefaultContext();
        $this->createPlugin($context);

        /** @var PluginEntity $plugin */
        $plugin = $this->createPluginManager()->getPluginByName(self::PLUGIN_NAME, $context);

        $this->performDefaultTests($plugin);
    }

    public function testGetPluginByNameThrowsException(): void
    {
        $context = Context::createDefaultContext();
        $this->createPlugin($context);

        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage('Plugin by name "SwagFoo" not found');
        $this->createPluginManager()->getPluginByName('SwagFoo', $context);
    }

    public function testInstallPlugin(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $sql = <<<SQL
        SELECT count(*)
FROM information_schema.TABLES
WHERE table_schema = DATABASE() AND table_name = :tableName;
SQL;
        $testTableExists = (bool) $this->connection->fetchColumn($sql, ['tableName' => \SwagTest\Migration\Migration1536761533Test::TABLE_NAME]);
        self::assertTrue($testTableExists);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $this->addTestPluginToKernel();
        $context = Context::createDefaultContext();
        $installedAt = (new DateTime())->format(Defaults::DATE_FORMAT);
        $this->createPlugin($context, self::PLUGIN_VERSION, $installedAt);
        $pluginManager = $this->createPluginManager();

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());
        self::assertSame($installedAt, $pluginInstalled->getInstalledAt()->format(Defaults::DATE_FORMAT));
    }

    public function testInstallPluginWithUpdate(): void
    {
        $this->addTestPluginToKernel();
        $context = Context::createDefaultContext();
        $this->createPlugin($context, self::PLUGIN_OLD_VERSION);
        $pluginManager = $this->createPluginManager();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());
        self::assertNotNull($pluginInstalled->getUpgradedAt());
        self::assertSame(self::PLUGIN_VERSION, $pluginInstalled->getVersion());
    }

    public function testUninstallPlugin(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);
        self::assertNotNull($pluginInstalled->getInstalledAt());

        $pluginManager->uninstallPlugin($pluginInstalled, $context);

        /** @var PluginEntity $pluginUninstalled */
        $pluginUninstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNull($pluginUninstalled->getInstalledAt());
        self::assertFalse($pluginUninstalled->getActive());
    }

    public function testUninstallPluginThrowsException(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $pluginManager->uninstallPlugin($plugin, $context);
    }

    public function testUpdatePlugin(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->updatePlugin($plugin, $context);

        /** @var PluginEntity $pluginUpdated */
        $pluginUpdated = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginUpdated->getUpgradedAt());
    }

    public function testActivatePlugin(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $pluginManager->activatePlugin($pluginInstalled, $context);

        /** @var PluginEntity $pluginActivated */
        $pluginActivated = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertTrue($pluginActivated->getActive());
    }

    public function testActivatePluginThrowsException(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $pluginManager->activatePlugin($plugin, $context);
    }

    public function testDeactivatePlugin(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $pluginManager->activatePlugin($pluginInstalled, $context);

        /** @var PluginEntity $pluginActivated */
        $pluginActivated = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertTrue($pluginActivated->getActive());

        $pluginManager->deactivatePlugin($pluginActivated, $context);

        /** @var PluginEntity $pluginDeactivated */
        $pluginDeactivated = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertFalse($pluginDeactivated->getActive());
    }

    public function testDeactivatePluginNotInstalledThrowsException(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $pluginManager->deactivatePlugin($plugin, $context);
    }

    public function testDeactivatePluginNotActivatedThrowsException(): void
    {
        $this->addTestPluginToKernel();
        $pluginManager = $this->createPluginManager();
        $context = Context::createDefaultContext();
        $pluginManager->refreshPlugins($context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        $pluginManager->installPlugin($plugin, $context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $pluginManager->getPluginByName(self::PLUGIN_NAME, $context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $this->expectException(PluginNotActivatedException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not activated at all');
        $pluginManager->deactivatePlugin($pluginInstalled, $context);
    }

    private function createPluginManager(): PluginManager
    {
        return new PluginManager(
            __DIR__ . '/_fixture',
            $this->kernel,
            $this->container->get(Connection::class),
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(MigrationCollection::class),
            $this->container->get(MigrationRuntime::class),
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->get(ComposerPackageProvider::class),
            $this->container->get('event_dispatcher')
        );
    }

    private function performDefaultTests(PluginEntity $plugin): void
    {
        self::assertSame(self::PLUGIN_NAME, $plugin->getName());
        self::assertSame(self::PLUGIN_LABEL, $plugin->getLabel());
        self::assertSame(self::PLUGIN_VERSION, $plugin->getVersion());
    }

    private function createPlugin(
        Context $context,
        string $version = self::PLUGIN_VERSION,
        ?string $installedAt = null
    ): void {
        $this->pluginRepo->create(
            [
                [
                    'name' => self::PLUGIN_NAME,
                    'version' => $version,
                    'label' => self::PLUGIN_LABEL,
                    'installedAt' => $installedAt,
                ],
            ],
            $context
        );
    }

    private function addTestPluginToKernel(): void
    {
        require_once __DIR__ . '/_fixture/SwagTest/SwagTest.php';
        $this->kernel::getPlugins()->add(new \SwagTest\SwagTest(false));
    }
}
