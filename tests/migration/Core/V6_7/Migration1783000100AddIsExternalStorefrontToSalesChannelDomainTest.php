<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783000100AddIsExternalStorefrontToSalesChannelDomain;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1783000100AddIsExternalStorefrontToSalesChannelDomain::class)]
class Migration1783000100AddIsExternalStorefrontToSalesChannelDomainTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1783000100,
            (new Migration1783000100AddIsExternalStorefrontToSalesChannelDomain())->getCreationTimestamp()
        );
    }

    public function testMigrate(): void
    {
        $this->rollback();

        // idempotent
        $this->migrate();
        $this->migrate();

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_domain', 'is_external_storefront'));

        $column = TableHelper::getColumnOfTable($this->connection, 'sales_channel_domain', 'is_external_storefront');
        static::assertTrue($column->isNotNull);
        static::assertSame('0', (string) $column->defaultValue);
    }

    private function migrate(): void
    {
        (new Migration1783000100AddIsExternalStorefrontToSalesChannelDomain())->update($this->connection);
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'sales_channel_domain', 'is_external_storefront')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP COLUMN `is_external_storefront`');
        }
    }
}
