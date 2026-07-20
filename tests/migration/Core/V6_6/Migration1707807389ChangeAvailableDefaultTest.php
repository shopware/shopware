<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1707807389ChangeAvailableDefault;

/**
 * @internal
 */
#[CoversClass(Migration1707807389ChangeAvailableDefault::class)]
class Migration1707807389ChangeAvailableDefaultTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1707807389, (new Migration1707807389ChangeAvailableDefault())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1707807389ChangeAvailableDefault();
        $migration->update($connection);

        $column = TableHelper::getColumnOfTable($connection, ProductDefinition::ENTITY_NAME, 'available');

        static::assertSame('0', $column->defaultValue);
    }
}
