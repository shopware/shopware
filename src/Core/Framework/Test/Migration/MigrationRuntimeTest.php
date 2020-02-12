<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
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

    protected function setUp(): void
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

    protected function tearDown(): void
    {
        $this->connection->executeQuery(
            'DELETE FROM `migration`
              WHERE `class` LIKE \'%_test_migrations_valid_run_time%\'
              OR `class` LIKE \'%_test_migrations_valid_run_time_exceptions%\''
        );
    }

    public function testItWorksWithASingleMigration(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(null, 1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testItWorksWithMultipleMigrations(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNotNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testItSkipsAlreadyExecutedMigrations(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNotNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $oldDate = $migrations[0]['update'];

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertSame($oldDate, $migrations[0]['update']);
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNotNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testNoDestructiveIfNoNoneDestructive(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testDestructiveIfOneNoneDestructive(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(null, 1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNotNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testDestructiveIfMultipleNoneDestructive(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNotNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrateDestructive();
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNotNull($migrations[0]['update_destructive']);
        static::assertNotNull($migrations[1]['update']);
        static::assertNotNull($migrations[1]['update_destructive']);
    }

    public function testTimestampCap(): void
    {
        $migrations = $this->getMigrations();
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);

        $runner = $this->runner->migrate(1);
        while ($runner->valid()) {
            $runner->next();
        }

        $migrations = $this->getMigrations();
        static::assertNotNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[1]['update']);
        static::assertNull($migrations[1]['update_destructive']);
    }

    public function testExceptionHandling(): void
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
        static::assertNull($migrations[0]['message']);
        static::assertNotNull($migrations[0]['update']);
        static::assertSame('update', $migrations[3]['message']);
        static::assertNull($migrations[3]['update']);
    }

    public function testExceptionHandlingDestructive(): void
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
        static::assertNull($migrations[0]['message']);
        static::assertNotNull($migrations[0]['update_destructive']);
        static::assertSame('update destructive', $migrations[2]['message']);
        static::assertNull($migrations[2]['update_destructive']);
        static::assertNull($migrations[3]['update_destructive']);
        static::assertSame('update', $migrations[3]['message']);
    }

    public function testIgnoreingInvalidMigrations(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger
            ->expects(static::once())
            ->method('notice')
            ->willReturn(null);

        $connection = $this->createMock(Connection::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);

        $statement = $this->createMock(Statement::class);
        $statement->method('fetchAll')->willReturn(['WrongClass']);

        $queryBuilder->method('execute')->willReturn($statement);

        $connection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $runtime = new MigrationRuntime($connection, $logger);

        iterator_to_array($runtime->migrate(), true);
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
