<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1763125891AddProductTypeColumn;

/**
 * @internal
 *
 * Regression coverage for issue #16240 (Cannot drop index '<unknown key name>'
 * when adding the product.type column on MySQL 8.4 via the
 * restrict_fk_on_non_standard_key guard introduced by MySQL bug #118151).
 *
 * The bug only fires on MySQL 8.4+ when a child table holds a foreign key
 * referencing a non-standard (non-PK / non-unique) key on `product`. The
 * fixture below creates that condition with a temporary child table.
 */
#[CoversClass(Migration1763125891AddProductTypeColumn::class)]
class Migration1763125891AddProductTypeColumnMysql84Test extends TestCase
{
    private const NON_STD_CHILD_TABLE = '_t_nonstd_fk_child_for_16240';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->skipUnlessMysql84WithFkGuardOn();
        $this->ensureStatesColumnExists();
        $this->dropTypeColumnIfExists();
        $this->createNonStandardChildFk();
    }

    protected function tearDown(): void
    {
        $this->dropNonStandardChildFk();
        $this->dropTypeColumnIfExists();
        parent::tearDown();
    }

    /**
     * Regression witness: with a non-standard child FK in place, a plain
     * ALTER TABLE on `product` must fail with the issue #16240 error. If this
     * test passes (no exception), the local environment can no longer reproduce
     * the bug — the fix oracle below is meaningless without it.
     */
    public function testRawAlterReproducesIssue16240(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/needed in a foreign key constraint/i');

        $this->connection->executeStatement(
            'ALTER TABLE `product` ADD COLUMN `_repro_type` VARCHAR(32) NOT NULL DEFAULT \'physical\''
        );
    }

    /**
     * Fix oracle: with the same fixture, the actual migration must complete
     * because it disables the FK guard for the duration of the DDL.
     */
    public function testMigrationSucceedsDespiteNonStandardChildFk(): void
    {
        (new Migration1763125891AddProductTypeColumn())->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'type'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.type'));
        static::assertSame(
            '1',
            (string) $this->connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key'),
            'Migration must restore the FK guard to its previous (ON) state'
        );
    }

    private function skipUnlessMysql84WithFkGuardOn(): void
    {
        $version = (string) $this->connection->fetchOne('SELECT VERSION()');
        if (stripos($version, 'mariadb') !== false) {
            static::markTestSkipped('MariaDB has no restrict_fk_on_non_standard_key — bug only manifests on MySQL 8.4+');
        }
        if (version_compare($version, '8.4.0', '<')) {
            static::markTestSkipped("MySQL {$version} does not enforce restrict_fk_on_non_standard_key");
        }
        $guard = (string) $this->connection->fetchOne('SELECT @@GLOBAL.restrict_fk_on_non_standard_key');
        if ($guard !== '1') {
            static::markTestSkipped('Global restrict_fk_on_non_standard_key is OFF — repro requires guard ON');
        }
    }

    private function createNonStandardChildFk(): void
    {
        $this->dropNonStandardChildFk();

        $childTable = self::NON_STD_CHILD_TABLE;
        $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');
        $this->connection->executeStatement(\sprintf(
            'CREATE TABLE `%s` (
                `id` BINARY(16) NOT NULL,
                `tax_id` BINARY(16) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk._t_nonstd_fk_child.tax_id`
                    FOREIGN KEY (`tax_id`) REFERENCES `product` (`tax_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $childTable
        ));
        $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = ON');
    }

    private function dropNonStandardChildFk(): void
    {
        $this->connection->executeStatement(\sprintf(
            'DROP TABLE IF EXISTS `%s`',
            self::NON_STD_CHILD_TABLE
        ));
    }

    private function dropTypeColumnIfExists(): void
    {
        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.type` ON `product`');
        }
        if (TableHelper::columnExists($this->connection, 'product', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `type`');
        }
        if (TableHelper::columnExists($this->connection, 'product', '_repro_type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `_repro_type`');
        }
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'states')) {
            return;
        }
        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
    }
}
