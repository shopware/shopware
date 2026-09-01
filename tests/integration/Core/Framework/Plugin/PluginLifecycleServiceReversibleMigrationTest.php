<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use SwagTestReversibleMigration\Migration\Migration1900000001CreateTable;

/**
 * Covers the reversible migration hooks in PluginLifecycleService.
 *
 * Deliberately not wrapped in a transaction: the fixture migration issues DDL, which MySQL commits
 * implicitly, so an outer transaction would be silently broken. State is cleaned up explicitly.
 *
 * @internal
 */
#[Package('framework')]
class PluginLifecycleServiceReversibleMigrationTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const PLUGIN_NAME = 'SwagTestReversibleMigration';

    private Connection $connection;

    private PluginService $pluginService;

    private PluginLifecycleService $pluginLifecycleService;

    /**
     * @var EntityRepository<PluginCollection>
     */
    private EntityRepository $pluginRepo;

    private Context $context;

    protected function setUp(): void
    {
        KernelLifecycleManager::bootKernel();

        // dedicated fixture directory: refreshPlugins() scans it wholesale, so it must contain
        // only this plugin, or the refresh would leak rows for every other fixture plugin
        $fixturePath = __DIR__ . '/_fixtures/reversible-plugins/';
        $container = static::getContainer();

        $this->connection = $container->get(Connection::class);
        $this->pluginRepo = $container->get('plugin.repository');
        $this->pluginLifecycleService = $container->get(PluginLifecycleService::class);
        $this->pluginService = $this->createPluginService(
            $fixturePath,
            $container->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $container->get('language.repository'),
            $container->get(PluginFinder::class)
        );

        require_once $fixturePath . self::PLUGIN_NAME . '/src/Migration/Migration1900000001CreateTable.php';
        $this->addTestPluginToKernel($fixturePath . self::PLUGIN_NAME, self::PLUGIN_NAME);

        $this->context = Context::createDefaultContext();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    public function testInstallRunsUpWithTheInstallationFlagSet(): void
    {
        $this->pluginLifecycleService->installPlugin($this->refreshedPlugin(), $this->context);

        static::assertTrue($this->tableExists());
        static::assertSame(
            [Migration1900000001CreateTable::class => (string) Migration1900000001CreateTable::TIMESTAMP],
            $this->history()
        );

        // the lifecycle must tell the migration that this is a fresh plugin installation
        static::assertSame('1', $this->connection->fetchOne(
            \sprintf('SELECT `was_installation` FROM `%s`', Migration1900000001CreateTable::TABLE)
        ));
    }

    public function testUninstallRunsDownAndClearsHistory(): void
    {
        $this->pluginLifecycleService->installPlugin($this->refreshedPlugin(), $this->context);

        $this->pluginLifecycleService->uninstallPlugin($this->installedPlugin(), $this->context);

        static::assertFalse($this->tableExists());
        static::assertSame([], $this->history());
    }

    public function testUninstallWithKeepUserDataLeavesSchemaAndHistoryIntact(): void
    {
        $this->pluginLifecycleService->installPlugin($this->refreshedPlugin(), $this->context);

        $this->pluginLifecycleService->uninstallPlugin($this->installedPlugin(), $this->context, true);

        static::assertTrue($this->tableExists());
        static::assertCount(1, $this->history());
    }

    private function refreshedPlugin(): PluginEntity
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
    }

    private function installedPlugin(): PluginEntity
    {
        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
    }

    private function tableExists(): bool
    {
        return $this->connection->fetchOne(
            'SHOW TABLES LIKE :table',
            ['table' => Migration1900000001CreateTable::TABLE]
        ) !== false;
    }

    /**
     * @return array<string, string>
     */
    private function history(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT `migration_class`, `creation_timestamp` FROM `plugin_migration` WHERE `plugin_name` = :plugin',
            ['plugin' => self::PLUGIN_NAME]
        );
    }

    private function cleanUp(): void
    {
        $this->connection->executeStatement(
            \sprintf('DROP TABLE IF EXISTS `%s`', Migration1900000001CreateTable::TABLE)
        );
        $this->connection->executeStatement(
            'DELETE FROM `plugin_migration` WHERE `plugin_name` = :plugin',
            ['plugin' => self::PLUGIN_NAME]
        );
        $this->connection->executeStatement(
            'DELETE FROM `plugin` WHERE `name` = :plugin',
            ['plugin' => self::PLUGIN_NAME]
        );
    }
}
