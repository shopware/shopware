<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773320557FixSalesChannelAnalyticsConstraint;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1773320557FixSalesChannelAnalyticsConstraint::class)]
class Migration1773320557FixSalesChannelAnalyticsConstraintTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('
                ALTER TABLE `sales_channel` DROP FOREIGN KEY `fk.sales_channel.analytics_id`;
            ');
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        $migration = new Migration1773320557FixSalesChannelAnalyticsConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKey = TableHelper::getForeignKeyOfTable($this->connection, SalesChannelDefinition::ENTITY_NAME, 'fk.sales_channel.analytics_id');
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKey->onDeleteAction);
    }
}
