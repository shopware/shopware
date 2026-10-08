<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787136514AddPriceBasisToCustomerGroup;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1787136514AddPriceBasisToCustomerGroup::class)]
class Migration1787136514AddPriceBasisToCustomerGroupTest extends TestCase
{
    private Connection $connection;

    private string $netDisplayGroupId;

    private string $grossDisplayGroupId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->netDisplayGroupId = Uuid::randomBytes();
        $this->grossDisplayGroupId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `customer_group` WHERE `id` IN (:net, :gross)',
            ['net' => $this->netDisplayGroupId, 'gross' => $this->grossDisplayGroupId]
        );

        (new Migration1787136514AddPriceBasisToCustomerGroup())->update($this->connection);
    }

    public function testMigrationAddsANonNullableColumnWithTheBasisMatchingTheDisplayMode(): void
    {
        $this->rollback();
        $this->insertGroup($this->netDisplayGroupId, displayGross: false);
        $this->insertGroup($this->grossDisplayGroupId, displayGross: true);

        (new Migration1787136514AddPriceBasisToCustomerGroup())->update($this->connection);

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(CustomerGroupDefinition::ENTITY_NAME)
            ->getColumn('price_basis');

        static::assertTrue($column->getNotnull());
        static::assertSame('gross', $column->getDefault());
        static::assertSame('net', $this->priceBasisOf($this->netDisplayGroupId));
        static::assertSame('gross', $this->priceBasisOf($this->grossDisplayGroupId));
        static::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `customer_group` WHERE `display_gross` = 0 AND `price_basis` = \'gross\'')
        );
    }

    public function testASecondRunKeepsALaterChoice(): void
    {
        $this->rollback();
        $this->insertGroup($this->netDisplayGroupId, displayGross: false);

        $migration = new Migration1787136514AddPriceBasisToCustomerGroup();
        $migration->update($this->connection);

        $this->connection->executeStatement(
            'UPDATE `customer_group` SET `price_basis` = \'gross\' WHERE `id` = :id',
            ['id' => $this->netDisplayGroupId]
        );

        $migration->update($this->connection);

        static::assertSame('gross', $this->priceBasisOf($this->netDisplayGroupId));
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1787136514,
            (new Migration1787136514AddPriceBasisToCustomerGroup())->getCreationTimestamp()
        );
    }

    private function insertGroup(string $id, bool $displayGross): void
    {
        $this->connection->insert('customer_group', [
            'id' => $id,
            'display_gross' => $displayGross ? 1 : 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function priceBasisOf(string $id): string
    {
        $priceBasis = $this->connection->fetchOne('SELECT `price_basis` FROM `customer_group` WHERE `id` = :id', ['id' => $id]);
        static::assertIsString($priceBasis);

        return $priceBasis;
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `customer_group` DROP COLUMN `price_basis`');
    }
}
