<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768233956AddThemeRuntimeConfigUniqueConstraint;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1768233956AddThemeRuntimeConfigUniqueConstraint::class)]
class Migration1768233956AddThemeRuntimeConfigUniqueConstraintTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1768233956AddThemeRuntimeConfigUniqueConstraint();
        static::assertSame(1768233956, $migration->getCreationTimestamp());
    }

    public function testMigrationRemovesDuplicatesAndAddsUniqueIndex(): void
    {
        $this->rollback();

        // Insert duplicate entries with the same technical_name
        $technicalName = 'TestTheme_' . Uuid::randomHex();

        $this->connection->insert('theme_runtime_config', [
            'theme_id' => Uuid::randomBytes(),
            'technical_name' => $technicalName,
            'resolved_config' => '{}',
            'view_inheritance' => '[]',
            'script_files' => '[]',
            'icon_sets' => '{}',
            'updated_at' => '2025-01-01 00:00:00.000',
        ]);
        $this->connection->insert('theme_runtime_config', [
            'theme_id' => Uuid::randomBytes(),
            'technical_name' => $technicalName,
            'resolved_config' => '{}',
            'view_inheritance' => '[]',
            'script_files' => '[]',
            'icon_sets' => '{}',
            'updated_at' => '2025-01-01 00:00:00.000',
        ]);

        $newThemeId = Uuid::randomBytes();
        $this->connection->insert('theme_runtime_config', [
            'theme_id' => $newThemeId,
            'technical_name' => $technicalName,
            'resolved_config' => '{}',
            'view_inheritance' => '[]',
            'script_files' => '[]',
            'icon_sets' => '{}',
            'updated_at' => '2025-06-01 00:00:00.000',
        ]);

        $countQuery = 'SELECT COUNT(*) FROM theme_runtime_config WHERE technical_name = :name';

        // Verify duplicates exist before migration
        $countBefore = (int) $this->connection->fetchOne($countQuery, ['name' => $technicalName]);
        static::assertSame(3, $countBefore);

        $migration = new Migration1768233956AddThemeRuntimeConfigUniqueConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection); // validate idempotency

        // Verify duplicates are removed, only newest remains
        $countAfter = (int) $this->connection->fetchOne($countQuery, ['name' => $technicalName]);
        static::assertSame(1, $countAfter);

        // Verify the newer entry (newThemeId) is kept
        $remainingThemeId = $this->connection->fetchOne(
            'SELECT theme_id FROM theme_runtime_config WHERE technical_name = :name',
            ['name' => $technicalName]
        );
        static::assertSame($newThemeId, $remainingThemeId);

        // Verify old index is gone and new unique index exists
        static::assertFalse(TableHelper::indexExists($this->connection, 'theme_runtime_config', 'idx.technical_name'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'theme_runtime_config', 'uidx.technical_name'));
        $index = TableHelper::getIndexOfTable($this->connection, 'theme_runtime_config', 'uidx.technical_name');
        static::assertSame(IndexType::UNIQUE->name, $index->type);

        // Cleanup
        $this->connection->delete('theme_runtime_config', ['theme_id' => $newThemeId]);
    }

    private function rollback(): void
    {
        // Drop unique index if exists
        if (TableHelper::indexExists($this->connection, 'theme_runtime_config', 'uidx.technical_name')) {
            $this->connection->executeStatement('ALTER TABLE `theme_runtime_config` DROP INDEX `uidx.technical_name`');
        }

        // Re-add non-unique index if not exists
        if (!TableHelper::indexExists($this->connection, 'theme_runtime_config', 'idx.technical_name')) {
            $this->connection->executeStatement('CREATE INDEX `idx.technical_name` ON `theme_runtime_config` (`technical_name`)');
        }
    }
}
