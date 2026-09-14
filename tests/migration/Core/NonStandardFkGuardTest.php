<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Migration\NonStandardFkGuard;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1707807389ChangeAvailableDefault;
use Shopware\Core\Migration\V6_6\Migration1714659357CanonicalProductVersion;

/**
 * Proves the migrations doing DDL on `product` survive MySQL bug #118151 with a non-standard
 * foreign key against that table in place.
 *
 * Requires MySQL 8.4+ with `restrict_fk_on_non_standard_key=ON`; skips elsewhere.
 *
 * @internal
 */
#[CoversClass(NonStandardFkGuard::class)]
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

        if ($this->productColumnExists('_fk_guard_probe')) {
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
    }

    #[DataProvider('migrationProvider')]
    public function testUpdateSurvivesNonStandardChildFk(MigrationStep $migration): void
    {
        $migration->update($this->connection);

        $this->assertGuardRestored();
    }

    public function testExecuteDdlStatementRetriesWithRelaxedGuard(): void
    {
        // Direct probe for the retry helper: the raw ALTER trips bug #118151 first, the retry with
        // the guard relaxed must succeed and restore the guard. tearDown drops the probe column.
        (new ProbeColumnMigration())->update($this->connection);

        static::assertTrue($this->productColumnExists('_fk_guard_probe'));
        $this->assertGuardRestored();
    }

    public function testUnguardedDdlStillFailsSoTheFixtureStaysMeaningful(): void
    {
        // Regression witness: if this stops throwing, the fixture no longer reproduces the bug and
        // the assertions above would pass for the wrong reason.
        $this->expectExceptionMessageMatches('/Cannot drop index \'<unknown key name>\'/');

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `_fk_guard_probe` VARCHAR(8) NULL');
    }

    private function productColumnExists(string $column): bool
    {
        return $this->connection->fetchOne(
            'SHOW COLUMNS FROM `product` WHERE `Field` = :column',
            ['column' => $column]
        ) !== false;
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
