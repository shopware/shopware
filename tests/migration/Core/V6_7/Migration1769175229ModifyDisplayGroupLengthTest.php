<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1769175229ModifyDisplayGroupLength;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1769175229ModifyDisplayGroupLength::class)]
class Migration1769175229ModifyDisplayGroupLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1769175229ModifyDisplayGroupLength();
        static::assertSame(1769175229, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1769175229ModifyDisplayGroupLength();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'product', 'display_group');
        static::assertSame(64, $column->length);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY COLUMN `display_group` VARCHAR(50) NULL;');
    }
}
