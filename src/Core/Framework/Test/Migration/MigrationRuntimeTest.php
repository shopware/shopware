<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationRuntimeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var MigrationRuntime
     */
    private $runner;

    protected function setUp()
    {
        $container = self::getKernel()->getContainer();

        $this->connection = $container->get(Connection::class);
        $this->runner = $container->get(MigrationRuntime::class);

        $collector = $this->getCollector();
        $collector->addDirectory(
            __DIR__ . '/_test_migrations_valid_run_time',
            'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time'
        );
        $collector->syncMigrationCollection();
    }

    protected function tearDown()
    {
        $this->connection->executeQuery(
            'DELETE FROM `migration`
              WHERE `class` LIKE \'%_test_migrations_valid_run_time%\'
              OR `class` LIKE \'%_test_migrations_valid_run_time_exceptions%\''
        );
    }

    public function test_it_works_with_a_single_migration()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(null, 1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_it_works_with_multiple_migrations()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNotNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_it_skips_already_executed_migrations()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNotNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $oldDate = $migrations[0]['update'];

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertSame($oldDate, $migrations[0]['update']);
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNotNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_no_destructive_if_no_none_destructive()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_destructive_if_one_none_destructive()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(null, 1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNotNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_destructive_if_multiple_none_destructive()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNotNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNotNull($migrations[0]['update_destructive']);
        self::assertNotNull($migrations[1]['update']);
        self::assertNotNull($migrations[1]['update_destructive']);
    }

    public function test_timestamp_cap()
    {
        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        self::assertNotNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[1]['update']);
        self::assertNull($migrations[1]['update_destructive']);
    }

    public function test_exception_handling()
    {
        $collector = $this->getCollector();
        $collector->addDirectory(
            __DIR__ . '/_test_migrations_valid_run_time_exceptions',
            'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions'
        );
        $collector->syncMigrationCollection();

        try {
            $runner = $this->runner->migrate();
            while ($runner->valid()) {
                $runner->next();
            }
        } catch (\Exception $e) {
            //nth
        }

        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['message']);
        self::assertNotNull($migrations[0]['update']);
        self::assertSame('update', $migrations[3]['message']);
        self::assertNull($migrations[3]['update']);
    }

    public function test_exception_handling_destructive()
    {
        $collector = $this->getCollector();
        $collector->addDirectory(
            __DIR__ . '/_test_migrations_valid_run_time_exceptions',
            'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions'
        );
        $collector->syncMigrationCollection();

        try {
            $runner = $this->runner->migrate();
            while ($runner->valid()) {
                $runner->next();
            }
        } catch (\Exception $e) {
            //nth
        }

        try {
            $runner = $this->runner->migrateDestructive();
            while ($runner->valid()) {
                $runner->next();
            }
        } catch (\Exception $e) {
            //nth
        }

        $migrations = $this->getMigrations();
        self::assertNull($migrations[0]['message']);
        self::assertNotNull($migrations[0]['update_destructive']);
        self::assertSame('update destructive', $migrations[2]['message']);
        self::assertNull($migrations[2]['update_destructive']);
        self::assertNull($migrations[3]['update_destructive']);
        self::assertSame('update', $migrations[3]['message']);
    }

    private function getCollector(): MigrationCollectionLoader
    {
        return new MigrationCollectionLoader($this->connection);
    }

    private function getMigrations(): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('migration')
            ->where('`class` LIKE \'%_test_migrations_valid_run_time%\'
              OR `class` LIKE \'%_test_migrations_valid_run_time_exceptions%\'')
            ->orderBy('creation_timestamp', 'ASC')
            ->execute()
            ->fetchAll();
    }
}
