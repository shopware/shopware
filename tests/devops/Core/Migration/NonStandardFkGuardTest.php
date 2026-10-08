<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1707807389ChangeAvailableDefault;
use Shopware\Core\Migration\V6_6\Migration1714659357CanonicalProductVersion;
use Shopware\Core\Migration\V6_6\Migration1726049442UpdateVariantListingConfigInProductTable;
use Shopware\Core\Migration\V6_7\Migration1756305375AddCategoriesIndexToProduct;
use Shopware\Core\Migration\V6_7\Migration1761739065IncreaseProductWeightPrecision;
use Shopware\Core\Migration\V6_7\Migration1763125891AddProductTypeColumn;
use Shopware\Core\Migration\V6_7\Migration1774345867AddProductOpenGraphFields;
use Shopware\Core\Migration\V6_7\Migration1775200001IncreaseProductDisplayGroupLength;
use Shopware\Core\Migration\V6_8\Migration1763125892RemoveProductStatesColumn;

/**
 * Proves the migrations doing DDL on `product` survive MySQL bug #118151 with a non-standard
 * foreign key against that table in place.
 *
 * Requires MySQL 8.4+ with `restrict_fk_on_non_standard_key=ON`; skips elsewhere. Run by the
 * `mysql:8.4` devops lane, see .github/bin/generate-phpunit-matrix.php.
 *
 * @internal
 */
#[Package('framework')]
class NonStandardFkGuardTest extends TestCase
{
    private const CHILD_TABLE = '_t_nonstd_fk_child_for_test';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $guard = $this->connection->fetchAssociative('SHOW SESSION VARIABLES LIKE \'restrict_fk_on_non_standard_key\'');
        if ($guard === false) {
            static::markTestSkipped('Server has no restrict_fk_on_non_standard_key — bug #118151 only affects MySQL 8.4+');
        }
        if ($guard['Value'] !== 'ON') {
            static::markTestSkipped('restrict_fk_on_non_standard_key is OFF — the repro needs the guard ON');
        }

        $this->createNonStandardChildFk();
    }

    protected function tearDown(): void
    {
        // Drop the fixture first, otherwise the cleanup below trips bug #118151 itself.
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', self::CHILD_TABLE));

        if (TableHelper::columnExists($this->connection, 'product', '_fk_guard_probe')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `_fk_guard_probe`');
        }
    }

    /**
     * @return iterable<string, array{MigrationStep}>
     */
    public static function migrationProvider(): iterable
    {
        yield 'ChangeAvailableDefault' => [new Migration1707807389ChangeAvailableDefault()];
        yield 'CanonicalProductVersion' => [new Migration1714659357CanonicalProductVersion()];
        yield 'UpdateVariantListingConfig' => [new Migration1726049442UpdateVariantListingConfigInProductTable()];
        yield 'AddCategoriesIndexToProduct' => [new Migration1756305375AddCategoriesIndexToProduct()];
        yield 'IncreaseProductWeightPrecision' => [new Migration1761739065IncreaseProductWeightPrecision()];
        yield 'AddProductTypeColumn' => [new Migration1763125891AddProductTypeColumn()];
        yield 'AddProductOpenGraphFields' => [new Migration1774345867AddProductOpenGraphFields()];
        yield 'IncreaseProductDisplayGroupLength' => [new Migration1775200001IncreaseProductDisplayGroupLength()];
    }

    #[DataProvider('migrationProvider')]
    public function testUpdateSurvivesNonStandardChildFk(MigrationStep $migration): void
    {
        $migration->update($this->connection);

        $this->assertGuardRestored();
    }

    public function testUpdateDestructiveSurvivesNonStandardChildFk(): void
    {
        // The only product migration with DDL in updateDestructive(). It really drops the column and
        // the devops suite shares its database, so capture the definition and restore it below.
        // Otherwise dal:validate fails on ProductDefinition while `states` is missing.
        $states = $this->connection->fetchAssociative(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = \'product\' AND COLUMN_NAME = \'states\'',
            ['schema' => $this->connection->getDatabase()]
        );
        static::assertIsArray($states, 'product.states must be present before the destructive migration runs');

        try {
            (new Migration1763125892RemoveProductStatesColumn())->updateDestructive($this->connection);

            static::assertFalse(TableHelper::columnExists($this->connection, 'product', 'states'));
            $this->assertGuardRestored();
        } finally {
            $this->restoreProductStatesColumn($states);
        }
    }

    public function testExecuteDdlStatementRetriesWithRelaxedGuard(): void
    {
        // Direct probe for the retry helper: the raw ALTER trips bug #118151 first, the retry with
        // the guard relaxed must succeed and restore the guard. tearDown drops the probe column.
        (new ProbeColumnMigration())->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', '_fk_guard_probe'));
        $this->assertGuardRestored();
    }

    public function testUnguardedDdlStillFailsSoTheFixtureStaysMeaningful(): void
    {
        // Regression witness: if this stops throwing, the fixture no longer reproduces the bug and
        // the assertions above would pass for the wrong reason.
        $this->expectExceptionMessageMatches('/Cannot drop index \'<unknown key name>\'/');

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `_fk_guard_probe` VARCHAR(8) NULL');
    }

    /**
     * @param array<string, mixed> $states definition captured from information_schema
     */
    private function restoreProductStatesColumn(array $states): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'states')) {
            return;
        }

        $sql = \sprintf(
            'ALTER TABLE `product` ADD COLUMN `states` %s %s',
            (string) $states['COLUMN_TYPE'],
            $states['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL'
        );

        if ($states['COLUMN_DEFAULT'] !== null) {
            $sql .= ' DEFAULT ' . $this->connection->quote((string) $states['COLUMN_DEFAULT']);
        }

        // The fixture is still installed, so the restore needs the guard relaxed too.
        $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');

        try {
            $this->connection->executeStatement($sql);
        } finally {
            $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = ON');
        }
    }

    private function assertGuardRestored(): void
    {
        $guard = $this->connection->fetchAssociative('SHOW SESSION VARIABLES LIKE \'restrict_fk_on_non_standard_key\'');

        static::assertIsArray($guard);
        static::assertSame('ON', $guard['Value'], 'The migration must restore the FK guard it relaxed');
    }

    /**
     * Adds a child table with a foreign key against the non-unique `product`.`tax_id`, which makes
     * every later ALTER on `product` trip bug #118151. Installing it needs the guard relaxed, just
     * as real shops acquired the drift on older servers.
     */
    private function createNonStandardChildFk(): void
    {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', self::CHILD_TABLE));

        $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');

        try {
            $this->connection->executeStatement(\sprintf(
                'CREATE TABLE `%s` (
                    `id` BINARY(16) NOT NULL,
                    `tax_id` BINARY(16) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk._t_nonstd_fk_child.tax_id`
                        FOREIGN KEY (`tax_id`) REFERENCES `product` (`tax_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                self::CHILD_TABLE
            ));
        } finally {
            $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = ON');
        }
    }
}

/**
 * @internal
 */
class ProbeColumnMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    public function update(Connection $connection): void
    {
        $this->executeDdlStatement($connection, 'ALTER TABLE `product` ADD COLUMN `_fk_guard_probe` VARCHAR(8) NULL');
    }
}
