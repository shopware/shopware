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

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->connection = $container->get(Connection::class);
        $this->collector = new MigrationCollection([]);
        $this->loader = new MigrationCollectionLoader($this->connection, $this->collector);
    }

    protected function tearDown(): void
    {
        $this->connection->executeQuery(
            'DELETE FROM `migration`
              WHERE `class` LIKE \'%_test_migrations_valid%\'
              OR `class` LIKE \'%_test_migrations_invalid_namespace%\''
        );
    }

    public function testItLoadsTheValidMigrations(): void
    {
        $this->collector->addDirectory(__DIR__ . '/_test_migrations_valid', 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid');
        $this->loader->syncMigrationCollection(self::MIGRATION_IDENTIFIER);

        $migrations = $this->getMigrations();

        $migrationsObjects = [];
        foreach ($migrations as $migration) {
            $migrationsObjects[] = new $migration['class']();
        }

        static::assertCount(2, $migrationsObjects);
        static::assertNull($migrations[0]['update']);
        static::assertNull($migrations[0]['update_destructive']);
        static::assertNull($migrations[0]['message']);
        static::assertNotNull($migrations[0]['class']);
        static::assertNotNull($migrations[0]['creation_timestamp']);
        static::assertEquals(1, $migrationsObjects[0]->getCreationTimestamp());
        static::assertEquals(2, $migrationsObjects[1]->getCreationTimestamp());
    }

    public function testItGetsCorrectMigrationTimestamps(): void
    {
        $this->collector->addDirectory(__DIR__ . '/_test_migrations_valid', 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid');
        $migrations = $this->collector->getActiveMigrationTimestamps();

        static::assertCount(2, $migrations);
        static::assertEquals(1, $migrations[0]);
        static::assertEquals(2, $migrations[1]);
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
