<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @group slow
 * @group skip-paratest
 */
class PluginLifecycleServiceMigrationTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;
    use MigrationTestBehaviour;

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

    public static function tearDownAfterClass(): void
    {
        $connection = Kernel::getConnection();

        $connection->executeUpdate('DELETE FROM migration WHERE `class` LIKE "SwagManualMigrationTest%"');
        $connection->executeUpdate('DELETE FROM plugin');

        KernelLifecycleManager::bootKernel();
    }

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->connection = $this->container->get(Connection::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();
        $this->context = Context::createDefaultContext();

        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->getParameter('kernel.project_dir'),
            $this->container->get(PluginFinder::class)
        );

        $this->addTestPluginToKernel('SwagManualMigrationTest');
        $this->requireMigrationFiles();

        $this->pluginService->refreshPlugins($this->context, new NullIO());
        $this->connection->executeUpdate('DELETE FROM plugin WHERE `name` = "SwagTest"');
    }

    public function testInstall(): MigrationCollection
    {
        static::assertSame(0, $this->connection->getTransactionNestingLevel());

        $migrationPlugin = $this->getMigrationTestPlugin();
        static::assertNull($migrationPlugin->getInstalledAt());

        $this->pluginLifecycleService->installPlugin($migrationPlugin, $this->context);
        $migrationCollection = $this->getMigrationCollection('SwagManualMigrationTest');
        $this->assertMigrationState($migrationCollection, 4, 1);

        return $migrationCollection;
    }

    /**
     * @depends testInstall
     */
    public function testActivate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->activatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 2);

        return $migrationCollection;
    }

    /**
     * @depends testActivate
     */
    public function testUpdate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->updatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        return $migrationCollection;
    }

    /**
     * @depends testUpdate
     */
    public function testDeactivate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->deactivatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        return $migrationCollection;
    }

    /**
     * @depends testDeactivate
     */
    public function testUninstallKeepUserData(MigrationCollection $migrationCollection): void
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->uninstallPlugin($migrationPlugin, $this->context, true);
        $this->assertMigrationCount($migrationCollection, 4);
    }

    private function assertMigrationCount(MigrationCollection $migrationCollection, int $expectedCount): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        /** @var MigrationSource $migrationSource */
        $migrationSource = ReflectionHelper::getPropertyValue($migrationCollection, 'migrationSource');

        $dbMigrations = $connection
            ->fetchAll(
                'SELECT * FROM `migration` WHERE `class` REGEXP :pattern ORDER BY `creation_timestamp`',
                ['pattern' => $migrationSource->getNamespacePattern()]
            );

        TestCase::assertCount($expectedCount, $dbMigrations);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->container->get(KernelPluginCollection::class),
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->container->get(SystemConfigService::class)
        );
    }

    private function getMigrationTestPlugin(): ?PluginEntity
    {
        return $this->pluginService
            ->getPluginByName('SwagManualMigrationTest', $this->context);
    }

    private function requireMigrationFiles(): void
    {
        require_once __DIR__ . '/_fixture/plugins/SwagManualMigrationTest/src/Migration/Migration1.php';
        require_once __DIR__ . '/_fixture/plugins/SwagManualMigrationTest/src/Migration/Migration2.php';
        require_once __DIR__ . '/_fixture/plugins/SwagManualMigrationTest/src/Migration/Migration3.php';
        require_once __DIR__ . '/_fixture/plugins/SwagManualMigrationTest/src/Migration/Migration4.php';
    }
}
