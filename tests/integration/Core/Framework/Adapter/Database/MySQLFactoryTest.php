<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Pdo\Mysql;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class MySQLFactoryTest extends TestCase
{
    use EnvTestBehaviour;
    use KernelTestBehaviour;

    public function testApplicationConnectionMatchesTheRegisteredFeatureFlag(): void
    {
        $params = static::getContainer()->get(Connection::class)->getParams();

        static::assertSame(!Feature::isActive('DISABLE_SQL_MULTI_STATEMENTS'), $params['driverOptions'][Mysql::ATTR_MULTI_STATEMENTS] ?? true);
    }

    public function testConnectionVariablesSurviveReconnecting(): void
    {
        $connection = MySQLFactory::create();

        try {
            for ($attempt = 0; $attempt < 2; ++$attempt) {
                static::assertSame('+00:00', $connection->fetchOne('SELECT @@session.time_zone'));
                static::assertGreaterThanOrEqual(320000, (int) $connection->fetchOne('SELECT @@session.group_concat_max_len'));
                static::assertStringNotContainsString('ONLY_FULL_GROUP_BY', (string) $connection->fetchOne('SELECT @@session.sql_mode'));
                $connection->close();
            }
        } finally {
            $connection->close();
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Remove the feature-flag guard when the flag is removed.
     */
    public function testApplicationConnectionRejectsMultipleStatements(): void
    {
        Feature::skipTestIfInActive('DISABLE_SQL_MULTI_STATEMENTS', $this);

        $this->expectException(SyntaxErrorException::class);

        static::getContainer()->get(Connection::class)->executeStatement('SET @multi_statement_test = 1; SET @multi_statement_test = 2');
    }

    /**
     * @deprecated tag:v6.8.0 - Remove the feature-flag guard when the flag is removed.
     */
    public function testApplicationConnectionRejectsMultiplePreparedStatements(): void
    {
        Feature::skipTestIfInActive('DISABLE_SQL_MULTI_STATEMENTS', $this);

        $this->expectException(SyntaxErrorException::class);

        static::getContainer()->get(Connection::class)->executeQuery('SELECT :first; SELECT :second', ['first' => 1, 'second' => 2]);
    }

    public function testMigrationsAllowMultipleStatementsAndShareTheirBookkeepingTransaction(): void
    {
        $container = static::getContainer();
        $connection = $container->get('doctrine.dbal.migration_connection');
        $applicationConnection = $container->get(Connection::class);

        static::assertNotSame($applicationConnection->fetchOne('SELECT CONNECTION_ID()'), $connection->fetchOne('SELECT CONNECTION_ID()'));

        $runtime = $container->get(MigrationRuntime::class);
        $source = new MigrationSource('multi_statements', [__DIR__ => __NAMESPACE__]);

        $connection->beginTransaction();

        try {
            $connection->insert('migration', [
                'class' => MultiStatementMigration::class,
                'creation_timestamp' => 1,
            ]);

            static::assertSame([MultiStatementMigration::class], iterator_to_array($runtime->migrate($source)));
            static::assertSame('2', $connection->fetchOne('SELECT @multi_statement_migration'));

            static::assertSame([MultiStatementMigration::class], iterator_to_array($runtime->migrateDestructive($source)));
            static::assertSame('4', $connection->fetchOne('SELECT @multi_statement_migration'));
            static::assertSame([], $runtime->getExecutableDestructiveMigrations($source));
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Remove the feature-flag guard when the flag is removed.
     */
    public function testPersistentApplicationConnectionRemainsHardenedAfterOpeningMigrationConnection(): void
    {
        Feature::skipTestIfInActive('DISABLE_SQL_MULTI_STATEMENTS', $this);
        $this->setEnvVars(['DATABASE_PERSISTENT_CONNECTION' => '1']);

        $connection = MySQLFactory::create();
        $migrationConnection = MySQLFactory::createMigrationConnection($connection);

        try {
            static::assertNotSame($connection->fetchOne('SELECT CONNECTION_ID()'), $migrationConnection->fetchOne('SELECT CONNECTION_ID()'));
            $migrationConnection->executeStatement('SET @migration_persistent_test = 1; SET @migration_persistent_test = 2');

            $this->expectException(SyntaxErrorException::class);
            $connection->executeStatement('SET @application_persistent_test = 1; SET @application_persistent_test = 2');
        } finally {
            $migrationConnection->close();
            $connection->close();
        }
    }
}

/**
 * @internal
 */
class MultiStatementMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET @multi_statement_migration = 1; SET @multi_statement_migration = 2');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('SET @multi_statement_migration = 3; SET @multi_statement_migration = 4');
    }
}
