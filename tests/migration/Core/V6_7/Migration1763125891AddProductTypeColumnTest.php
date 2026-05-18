<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1763125891AddProductTypeColumn;
use Shopware\Tests\Migration\MySql84FkGuardTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1763125891AddProductTypeColumn::class)]
class Migration1763125891AddProductTypeColumnTest extends TestCase
{
    use MySql84FkGuardTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763125891, (new Migration1763125891AddProductTypeColumn())->getCreationTimestamp());
    }

    public function testUpdateAddsTypeColumnAndIndex(): void
    {
        $this->ensureStatesColumnExists();
        $this->dropTypeColumnIfExists();

        $migration = new Migration1763125891AddProductTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $typeColumn = TableHelper::getColumnOfTable($this->connection, 'product', 'type');
        static::assertSame('physical', $typeColumn->defaultValue);
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.type'));
    }

    public function testUpdateAddsMissingIndexWhenTypeColumnAlreadyExists(): void
    {
        $this->ensureStatesColumnExists();
        $this->ensureTypeColumnExistsWithoutIndex();

        $migration = new Migration1763125891AddProductTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $typeColumn = TableHelper::getColumnOfTable($this->connection, 'product', 'type');
        static::assertSame('physical', $typeColumn->defaultValue);
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.type'));
    }

    /**
     * Regression coverage for issue #16240 / MySQL bug #118151. Skips outside
     * MySQL 8.4+ with `restrict_fk_on_non_standard_key=ON`.
     */
    public function testUpdateRunsOnMysql84WithNonStandardChildFkOnProduct(): void
    {
        $this->skipUnlessMysql84WithFkGuardOn($this->connection);
        $this->ensureStatesColumnExists();
        $this->dropTypeColumnIfExists();
        $this->createNonStandardChildFkOnProduct($this->connection);

        try {
            (new Migration1763125891AddProductTypeColumn())->update($this->connection);

            $typeColumn = TableHelper::getColumnOfTable($this->connection, 'product', 'type');
            static::assertSame('physical', $typeColumn->defaultValue);
            static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.type'));
            static::assertSame(
                '1',
                (string) $this->connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key'),
                'Migration must restore the FK guard to its previous (ON) state'
            );
        } finally {
            $this->dropNonStandardChildFkOnProduct($this->connection);
        }
    }

    private function dropTypeColumnIfExists(): void
    {
        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.type` ON `product`');
        }

        if (TableHelper::columnExists($this->connection, 'product', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `type`');
        }
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
    }

    private function ensureTypeColumnExistsWithoutIndex(): void
    {
        $this->dropTypeColumnIfExists();

        $this->connection->executeStatement(
            'ALTER TABLE `product` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT \'physical\''
        );
    }
}
