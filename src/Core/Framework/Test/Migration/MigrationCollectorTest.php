<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MIGRATION_IDENTIFIER = 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid';

    /**
     * @var Connection
     */
    private $connection;

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
        $this->collector = new MigrationCollection([]);
        $this->loader = new MigrationCollectionLoader($this->connection, $this->collector);
    }

    protected function tearDown()
    {
        $this->connection->executeQuery(
            'DELETE FROM `migration`
              WHERE `class` LIKE \'%_test_migrations_valid%\'
              OR `class` LIKE \'%_test_migrations_invalid_namespace%\''
        );
    }

    public function test_it_loads_the_valid_migrations(): void
    {
        $this->collector->addDirectory(__DIR__ . '/_test_migrations_valid', 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid');
        $this->loader->syncMigrationCollection(self::MIGRATION_IDENTIFIER);

        $migrations = $this->getMigrations();

        $migrationsObjects = [];
        foreach ($migrations as $migration) {
            $migrationsObjects[] = new $migration['class']();
        }

        self::assertCount(2, $migrationsObjects);
        self::assertNull($migrations[0]['update']);
        self::assertNull($migrations[0]['update_destructive']);
        self::assertNull($migrations[0]['message']);
        self::assertNotNull($migrations[0]['class']);
        self::assertNotNull($migrations[0]['creation_timestamp']);
        self::assertEquals(1, $migrationsObjects[0]->getCreationTimestamp());
        self::assertEquals(2, $migrationsObjects[1]->getCreationTimestamp());
    }

    public function test_it_gets_correct_migration_timestamps(): void
    {
        $this->collector->addDirectory(__DIR__ . '/_test_migrations_valid', 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid');
        $migrations = $this->collector->getActiveMigrationTimestamps();

        self::assertCount(2, $migrations);
        self::assertEquals(1, $migrations[0]);
        self::assertEquals(2, $migrations[1]);
    }

    private function getMigrations(): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('migration')
            ->where('`class` LIKE \'%_test_migrations_valid%\'')
            ->orderBy('creation_timestamp', 'ASC')
            ->execute()
            ->fetchAll();
    }
}
