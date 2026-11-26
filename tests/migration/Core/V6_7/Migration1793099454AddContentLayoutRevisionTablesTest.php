<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\Column;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1793099454AddContentLayoutRevisionTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793099454AddContentLayoutRevisionTables::class)]
class Migration1793099454AddContentLayoutRevisionTablesTest extends TestCase
{
    private const TABLES = [
        'content_layout_revision' => ['id', 'content_layout_id', 'parent_id', 'layout', 'created_by_id', 'published_at', 'published_by_id', 'last_live_at', 'live_content_layout_id', 'created_at'],
        'content_layout_draft' => ['id', 'content_layout_id', 'name', 'created_by_id', 'base_revision_id', 'head_revision_id', 'live_at_open_revision_id', 'created_at', 'updated_at'],
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793099454, (new Migration1793099454AddContentLayoutRevisionTables())->getCreationTimestamp());
    }

    public function testUpdateCreatesTheTablesAndIsIdempotent(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }

        $migration = new Migration1793099454AddContentLayoutRevisionTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table => $columns) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
            static::assertSame(
                $columns,
                array_map(static fn (Column $column): string => $column->name, TableHelper::getTable($this->connection, $table)->columns),
                $table,
            );
        }
    }

    public function testUpdateDestructiveDropsTheSupersededStorage(): void
    {
        if (!TableHelper::columnExists($this->connection, 'content_layout', 'revisions')) {
            $this->connection->executeStatement('ALTER TABLE `content_layout` ADD COLUMN `revisions` JSON NULL');
        }
        foreach (['content_layout_publication', 'content_layout_branch'] as $table) {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS `' . $table . '` (`id` BINARY(16) NOT NULL PRIMARY KEY)');
        }

        $migration = new Migration1793099454AddContentLayoutRevisionTables();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'content_layout', 'revisions'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'content_layout_publication'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'content_layout_branch'));
    }
}
