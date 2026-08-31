<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1786447612FixSalesChannelAnalyticsConstraint;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1786447612FixSalesChannelAnalyticsConstraint::class)]
class Migration1786447612FixSalesChannelAnalyticsConstraintTest extends TestCase
{
    private const FOREIGN_KEY_NAME = 'fk.sales_channel.analytics_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786447612, (new Migration1786447612FixSalesChannelAnalyticsConstraint())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->resetAnalyticsForeignKey();

        $foreignKeyBefore = TableHelper::getForeignKeyOfTable(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::CASCADE->value, $foreignKeyBefore->onDeleteAction);

        $migration = new Migration1786447612FixSalesChannelAnalyticsConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKeyAfter = TableHelper::getForeignKeyOfTable(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKeyAfter->onDeleteAction);
    }

    private function resetAnalyticsForeignKey(): void
    {
        try {
            $this->connection->executeStatement(\sprintf('
                ALTER TABLE `sales_channel`
                DROP FOREIGN KEY `%s`;
            ', self::FOREIGN_KEY_NAME));
        } catch (\Throwable) {
        }

        $this->connection->executeStatement(\sprintf('
            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `%s`
            FOREIGN KEY (`analytics_id`)
            REFERENCES `sales_channel_analytics` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE;
        ', self::FOREIGN_KEY_NAME));
    }
}
