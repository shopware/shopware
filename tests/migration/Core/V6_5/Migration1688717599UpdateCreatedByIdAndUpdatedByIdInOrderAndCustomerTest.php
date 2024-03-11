<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomer;

/**
 * @internal
 */
#[CoversClass(Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomer::class)]
class Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomerTest extends TestCase
{
    public function testUpdateRuleAssociationsToRestrict(): void
    {
        $conn = KernelLifecycleManager::getConnection();

        $database = $conn->fetchOne('select database();');

        $migration = new Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomer();
        $migration->update($conn);

        /** @var array<array<string, mixed>> $customerForeignKeyInfoUpdated */
        $customerForeignKeyInfoUpdated = $conn->fetchAllAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "customer" AND REFERENCED_TABLE_NAME = "user" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertCount(2, $customerForeignKeyInfoUpdated);
        static::assertEquals($customerForeignKeyInfoUpdated[0]['CONSTRAINT_NAME'], 'fk.customer.created_by_id');
        static::assertEquals($customerForeignKeyInfoUpdated[1]['CONSTRAINT_NAME'], 'fk.customer.updated_by_id');
        static::assertEquals($customerForeignKeyInfoUpdated[0]['DELETE_RULE'], 'SET NULL');
        static::assertEquals($customerForeignKeyInfoUpdated[1]['DELETE_RULE'], 'SET NULL');

        /** @var array<array<string, mixed>> $orderForeignKeyInfoUpdated */
        $orderForeignKeyInfoUpdated = $conn->fetchAllAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "order" AND REFERENCED_TABLE_NAME = "user" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertCount(2, $orderForeignKeyInfoUpdated);
        static::assertEquals($orderForeignKeyInfoUpdated[0]['CONSTRAINT_NAME'], 'fk.order.created_by_id');
        static::assertEquals($orderForeignKeyInfoUpdated[1]['CONSTRAINT_NAME'], 'fk.order.updated_by_id');
        static::assertEquals($orderForeignKeyInfoUpdated[0]['DELETE_RULE'], 'SET NULL');
        static::assertEquals($orderForeignKeyInfoUpdated[1]['DELETE_RULE'], 'SET NULL');
    }
}
