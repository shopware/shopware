<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1669124190AddDoctrineMessengerTable;

/**
 * @internal
 */
#[CoversClass(Migration1669124190AddDoctrineMessengerTable::class)]
class Migration1669124190AddDoctrineMessengerTableTest extends TestCase
{
    public function testItCreatesTable(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS `messenger_messages`');

        $migration = new Migration1669124190AddDoctrineMessengerTable();

        $migration->update($connection);
        // check that it can be executed multiple times
        $migration->update($connection);

        $tableExists = (bool) $connection->fetchOne('SHOW TABLES LIKE "messenger_messages"');
        static::assertTrue($tableExists);
    }
}
