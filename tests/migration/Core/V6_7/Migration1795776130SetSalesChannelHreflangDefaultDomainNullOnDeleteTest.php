<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDelete;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDelete::class)]
class Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDeleteTest extends TestCase
{
    private const FOREIGN_KEY_NAME = 'fk.sales_channel.hreflang_default_domain_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795776130, (new Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDelete())->getCreationTimestamp());
    }

    public function testHreflangDefaultDomainForeignKeySetsNullOnDelete(): void
    {
        $this->resetForeignKeyToCascade();

        $foreignKeyBefore = TableHelper::getForeignKeyOfTable(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::CASCADE->value, $foreignKeyBefore->onDeleteAction);

        $migration = new Migration1795776130SetSalesChannelHreflangDefaultDomainNullOnDelete();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKeyAfter = TableHelper::getForeignKeyOfTable(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKeyAfter->onDeleteAction);
        static::assertSame(['hreflang_default_domain_id'], $foreignKeyAfter->referencingColumnNames);
        static::assertSame('sales_channel_domain', $foreignKeyAfter->referencedTableName);
    }

    private function resetForeignKeyToCascade(): void
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
            FOREIGN KEY (`hreflang_default_domain_id`)
            REFERENCES `sales_channel_domain` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE;
        ', self::FOREIGN_KEY_NAME));
    }
}
