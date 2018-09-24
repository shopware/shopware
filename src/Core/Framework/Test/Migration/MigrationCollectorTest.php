<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var MigrationCollectionLoader
     */
    private $collector;

    protected function setUp()
    {
        $container = $this->getKernel()->getContainer();
        $this->connection = $container->get(Connection::class);
        $this->collector = new MigrationCollectionLoader($this->connection);
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
        $this->collector
            ->addDirectory(__DIR__ . '/_test_migrations_valid', 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid')
            ->syncMigrationCollection();

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

    public function test_it_throws_invalid_php_file(): void
    {
        $this->collector->addDirectory(__DIR__ . '/_test_migrations_invalid_namespace', 'Shopware\Core\Framework\Test\Migration\_test_migrations_invalid_namespace');

        $this->expectException(InvalidMigrationClassException::class);
        $this->collector->syncMigrationCollection();
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
