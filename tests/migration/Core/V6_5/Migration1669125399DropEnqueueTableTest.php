<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1669125399DropEnqueueTable;

/**
 * @internal
 */
#[CoversClass(Migration1669125399DropEnqueueTable::class)]
class Migration1669125399DropEnqueueTableTest extends TestCase
{
    public function testItDropsEnqueueTable(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `enqueue` (id int PRIMARY KEY)');

        $migration = new Migration1669125399DropEnqueueTable();

        $migration->updateDestructive($connection);
        // check that it can be executed multiple times
        $migration->updateDestructive($connection);

        $tableExists = (bool) $connection->fetchOne('SHOW TABLES LIKE "enqueue"');
        static::assertFalse($tableExists);
    }
}
