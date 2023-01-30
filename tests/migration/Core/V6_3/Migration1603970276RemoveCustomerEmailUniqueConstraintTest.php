<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1603970276RemoveCustomerEmailUniqueConstraint;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1603970276RemoveCustomerEmailUniqueConstraint
 */
class Migration1603970276RemoveCustomerEmailUniqueConstraintTest extends TestCase
{
    public function testCustomerEmailUniqueConstraintIsRemoved(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $conn->executeStatement('CREATE UNIQUE INDEX `uniq.customer.email_bound_sales_channel_id` ON `customer` (`email`, `sales_channel_id`);');

        $migration = new Migration1603970276RemoveCustomerEmailUniqueConstraint();
        $migration->update($conn);

        /** @var array<string, mixed>[] $indexes */
        $indexes = $conn->fetchAllAssociative('SHOW INDEX FROM `customer`;');

        $emailUniqueConstraint = array_filter($indexes, fn (array $index) => !empty($index['Key_name']) && $index['Key_name'] === 'uniq.customer.email_bound_sales_channel_id');

        static::assertEmpty($emailUniqueConstraint);
    }
}
