<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1775200001IncreaseProductDisplayGroupLength;

/**
 * @internal
 */
#[CoversClass(Migration1775200001IncreaseProductDisplayGroupLength::class)]
class Migration1775200001IncreaseProductDisplayGroupLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1775200001, (new Migration1775200001IncreaseProductDisplayGroupLength())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1775200001IncreaseProductDisplayGroupLength();
        static::assertSame(1775200001, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, ProductDefinition::ENTITY_NAME, 'display_group');

        static::assertSame('string', $column->type);
        static::assertSame(64, $column->length);
        static::assertFalse($column->isNotNull);
        static::assertNull($column->defaultValue);
    }

    public function testMigrationSkipsAlterWhenDisplayGroupAlreadyAtLeast64(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');

        (new Migration1775200001IncreaseProductDisplayGroupLength())->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, ProductDefinition::ENTITY_NAME, 'display_group');
        static::assertSame(64, $column->length);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(50) NULL');
    }
}
