<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1758018339ContentLayout;
use Shopware\Core\Migration\V6_7\Migration1782259200ContentLayoutRootSource;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1782259200ContentLayoutRootSource::class)]
class Migration1782259200ContentLayoutRootSourceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782259200, (new Migration1782259200ContentLayoutRootSource())->getCreationTimestamp());
    }

    public function testRootSourceColumnAttributes(): void
    {
        // addColumn is idempotent, so run it to guarantee the column exists regardless of test order.
        (new Migration1782259200ContentLayoutRootSource())->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => 'content_layout', 'column' => 'root_source']
        );

        static::assertIsArray($column);
        static::assertSame('varchar(255)', $column['COLUMN_TYPE']);
        static::assertSame('NO', $column['IS_NULLABLE']);
        // The migration adds the column with a literal empty-string default to satisfy the ALGORITHM=INSTANT
        // NOT-NULL add. Assert a default is present before normalising it, so a regression that drops the
        // default entirely (COLUMN_DEFAULT NULL) cannot pass via the (string) null === '' coincidence.
        static::assertNotNull($column['COLUMN_DEFAULT']);
        // MySQL and MariaDB both surface a string default with surrounding quotes in COLUMN_DEFAULT;
        // strip them so the assertion pins the empty-string default without coupling to the engine's quoting.
        static::assertSame('', trim((string) $column['COLUMN_DEFAULT'], '\''));
    }

    public function testMigration(): void
    {
        $base = new Migration1758018339ContentLayout();
        $migration = new Migration1782259200ContentLayoutRootSource();

        // content_layout is referenced by foreign keys from the *_content_layout assignment tables;
        // disable foreign key checks so the parent table can be dropped without touching its dependents
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Start from the original 1758018339 schema (root_source absent, schema present) to prove convergence.
            $this->connection->executeStatement('DROP TABLE IF EXISTS `content_layout`');
            $base->update($this->connection);

            static::assertFalse(TableHelper::columnExists($this->connection, 'content_layout', 'root_source'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'schema'));

            // Idempotent: running each step twice must not fail.
            $migration->update($this->connection);
            $migration->update($this->connection);
            $migration->updateDestructive($this->connection);
            $migration->updateDestructive($this->connection);

            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'root_source'));
            static::assertFalse(TableHelper::columnExists($this->connection, 'content_layout', 'schema'));
        } finally {
            // Nest the schema restore so a restore failure cannot leak FOREIGN_KEY_CHECKS=0 onto the shared
            // connection: re-enabling the checks is an independent cleanup that must run regardless of outcome.
            try {
                // Leave content_layout in the canonical post-migration schema so sibling tests keep a consistent state.
                $base->update($this->connection);
                $migration->update($this->connection);
                $migration->updateDestructive($this->connection);
            } finally {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}
