<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationRuntimeTest extends TestCase
{
    use IntegrationTestBehaviour;
    private const MIGRATION_IDENTIFIER = 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var MigrationRuntime
     */
    private $runner;

    /**
     * @var MigrationCollection
     */
    private $collector;

    /**
     * @var MigrationCollectionLoader
     */
    private $loader;

    protected function setUp()
    {
        $container = $this->getContainer();

        $this->connection = $container->get(Connection::class);
        $this->runner = $container->get(MigrationRuntime::class);

        $this->collector = new MigrationCollection([
                'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time' => __DIR__ . '/_test_migrations_valid_run_time',
        ]);
        $this->loader = new MigrationCollectionLoader($this->connection, $this->collector);

        $this->loader->syncMigrationCollection('Shopware\Core\Framework\Test\Migration');
    }

    protected function tearDown()
    {
        $this->connection->executeQuery(
            'DELETE FROM `migration`
              WHERE `class` LIKE \'%_test_migrations_valid_run_time%\'
              OR `class` LIKE \'%_test_migrations_valid_run_time_exceptions%\''
        );
    }

    public function test_it_works_with_a_single_migration(): void
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

    public function test_it_works_with_multiple_migrations(): void
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

    public function test_it_skips_already_executed_migrations(): void
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

    public function test_no_destructive_if_no_none_destructive(): void
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

    public function test_destructive_if_one_none_destructive(): void
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

    public function test_destructive_if_multiple_none_destructive(): void
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

    public function test_timestamp_cap(): void
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

    public function test_exception_handling(): void
    {
        $this->collector->addDirectory(
            __DIR__ . '/_test_migrations_valid_run_time_exceptions',
            'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions'
        );
        $this->loader->syncMigrationCollection(self::MIGRATION_IDENTIFIER);

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

    public function test_exception_handling_destructive(): void
    {
        $this->collector->addDirectory(
            __DIR__ . '/_test_migrations_valid_run_time_exceptions',
            'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions'
        );
        $this->loader->syncMigrationCollection(self::MIGRATION_IDENTIFIER);

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
