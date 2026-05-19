<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * Shared fixture for migration tests that need to prove they survive
 * MySQL bug #118151 — `restrict_fk_on_non_standard_key=ON` causing
 * `ALTER TABLE` / `CREATE INDEX` on a parent table to fail with
 * `Cannot drop index '<unknown key name>': needed in a foreign key
 * constraint` when any child FK references a non-standard key.
 *
 * Usage in a test method:
 *
 *     $this->skipUnlessMysql84WithFkGuardOn($connection);
 *     $this->createNonStandardChildFkOnProduct($connection);
 *     try {
 *         $this->runMigrationViaRuntime($connection, new MigrationXxx());
 *         // assert the migration's effect
 *     } finally {
 *         $this->dropNonStandardChildFkOnProduct($connection);
 *     }
 */
trait MySql84FkGuardTestTrait
{
    private const NON_STD_FK_TABLE = '_t_nonstd_fk_child_for_test';

    protected function skipUnlessMysql84WithFkGuardOn(Connection $connection): void
    {
        $version = (string) $connection->fetchOne('SELECT VERSION()');
        if (stripos($version, 'mariadb') !== false) {
            static::markTestSkipped('MariaDB has no restrict_fk_on_non_standard_key — bug #118151 only manifests on MySQL 8.4+');
        }
        if (version_compare($version, '8.4.0', '<')) {
            static::markTestSkipped(\sprintf('MySQL %s does not enforce restrict_fk_on_non_standard_key', $version));
        }
        $guard = (string) $connection->fetchOne('SELECT @@GLOBAL.restrict_fk_on_non_standard_key');
        if ($guard !== '1') {
            static::markTestSkipped('Global restrict_fk_on_non_standard_key is OFF — repro requires the guard ON');
        }
    }

    /**
     * Creates a temporary child table with a foreign key that references a
     * non-PK / non-unique column on `product`. This is the condition that
     * triggers MySQL bug #118151 on subsequent ALTER/CREATE INDEX statements
     * targeting `product`.
     */
    protected function createNonStandardChildFkOnProduct(Connection $connection): void
    {
        $this->dropNonStandardChildFkOnProduct($connection);

        // The guard refuses non-standard FK creation; flip it off only for
        // this DDL so the fixture itself can be installed.
        $connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');
        $connection->executeStatement(\sprintf(
            'CREATE TABLE `%s` (
                `id` BINARY(16) NOT NULL,
                `tax_id` BINARY(16) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk._t_nonstd_fk_child.tax_id`
                    FOREIGN KEY (`tax_id`) REFERENCES `product` (`tax_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            self::NON_STD_FK_TABLE
        ));
        $connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = ON');
    }

    protected function dropNonStandardChildFkOnProduct(Connection $connection): void
    {
        $connection->executeStatement(\sprintf(
            'DROP TABLE IF EXISTS `%s`',
            self::NON_STD_FK_TABLE
        ));
    }

    /**
     * Runs the migration through {@see MigrationRuntime::migrate()} so the
     * test exercises the real production code path — including the bug
     * #118151 retry — rather than calling `$migration->update()` directly
     * (which would fail on MySQL 8.4 with a non-standard child FK in place).
     *
     * `migrate()` skips migrations whose record has `update IS NOT NULL`.
     * After `system:install` every migration is recorded as executed, so the
     * test temporarily resets the record back to pending, runs the iterator
     * (it picks up only this class because no other migration in the same
     * namespace is pending), and the runtime's `setExecuted` re-stamps the
     * record on success. Two consecutive calls therefore exercise the
     * migration's own idempotency.
     */
    protected function runMigrationViaRuntime(Connection $connection, MigrationStep $migration): void
    {
        $class = $migration::class;
        $namespace = substr($class, 0, (int) strrpos($class, '\\'));

        $connection->update(
            'migration',
            ['`update`' => null],
            ['`class`' => $class]
        );

        $source = new MigrationSource('mysql84-fk-guard-test', [$namespace]);
        $runtime = new MigrationRuntime($connection, new NullLogger());

        iterator_to_array($runtime->migrate($source));
    }
}
