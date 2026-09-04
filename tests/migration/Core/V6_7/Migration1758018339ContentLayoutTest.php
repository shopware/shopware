<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1758018339ContentLayout;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1758018339ContentLayout::class)]
class Migration1758018339ContentLayoutTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1758018339, (new Migration1758018339ContentLayout())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1758018339ContentLayout();

        // content_layout is referenced by foreign keys from the *_content_layout assignment tables;
        // disable foreign key checks so the parent table can be dropped without touching its dependents
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `content_layout`');

            static::assertFalse(TableHelper::tableExists($this->connection, 'content_layout'));

            $migration->update($this->connection);
            $migration->update($this->connection);

            static::assertTrue(TableHelper::tableExists($this->connection, 'content_layout'));

            $table = TableHelper::getTable($this->connection, 'content_layout');
            static::assertCount(7, $table->columns);

            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'id'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'name'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'version'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'layout'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'schema'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'created_at'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'updated_at'));

            static::assertTrue(
                TableHelper::indexExists($this->connection, 'content_layout', 'uniq.content_layout.name_version'),
                'Unique index on (name, version) must exist',
            );
        } finally {
            // restore the parent table so its dependents and sibling tests keep a consistent schema, then re-enable enforcement
            $migration->update($this->connection);
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
