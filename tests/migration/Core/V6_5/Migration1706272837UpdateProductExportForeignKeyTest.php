<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1706272837UpdateProductExportForeignKey;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(Migration1706272837UpdateProductExportForeignKey::class)]
class Migration1706272837UpdateProductExportForeignKeyTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdatesForeignKey(): void
    {
        $migration = new Migration1706272837UpdateProductExportForeignKey();
        $migration->update($this->connection);

        $this->validateForeignKey();
    }

    public function testUpdatesEvenWhenForeignKeyMissing(): void
    {
        $migration = new Migration1706272837UpdateProductExportForeignKey();
        $this->connection->executeStatement('ALTER TABLE `product_export` DROP FOREIGN KEY `fk.product_export.sales_channel_domain_id`;');

        $migration->update($this->connection);

        $this->validateForeignKey();
    }

    public function testMultipleExecution(): void
    {
        $migration = new Migration1706272837UpdateProductExportForeignKey();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->validateForeignKey();
    }

    private function validateForeignKey(): void
    {
        $database = $this->connection->fetchOne('select database();');

        $customerForeignKeyInfoUpdated = $this->connection->fetchAllAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "product_export" AND REFERENCED_TABLE_NAME = "sales_channel_domain" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertCount(1, $customerForeignKeyInfoUpdated);
        static::assertEquals('fk.product_export.sales_channel_domain_id', $customerForeignKeyInfoUpdated[0]['CONSTRAINT_NAME']);
        static::assertEquals('RESTRICT', $customerForeignKeyInfoUpdated[0]['DELETE_RULE']);
    }
}
