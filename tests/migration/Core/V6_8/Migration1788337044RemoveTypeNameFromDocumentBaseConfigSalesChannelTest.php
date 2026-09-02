<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannel;

#[Package('after-sales')]
#[CoversClass(Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannel::class)]
class Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788337044, (new Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannel())->getCreationTimestamp());
    }

    public function testUpdateRemovesTypeNameFieldFromDocumentBaseConfigSalesChannel(): void
    {
        $this->ensureTemplateDataColumnExists();

        $migration = new Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannel();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'document_base_config_sales_channel', 'type_name'));
    }

    private function ensureTemplateDataColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'document_base_config_sales_channel', 'type_name')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `document_base_config_sales_channel` ADD COLUMN `type_name` VARCHAR(255) NULL');
    }
}
