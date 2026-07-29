<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullable;

/**
 * @internal
 */
#[CoversClass(Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullable::class)]
class Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1697788982, (new Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullable();
        static::assertSame(1697788982, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'shipping_method', 'availability_rule_id');
        static::assertFalse($column->isNotNull);
        static::assertNull($column->defaultValue);
    }
}
