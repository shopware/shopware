<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1621845357AddFlow;
use Shopware\Core\Migration\V6_4\Migration1621845370AddFlowSequence;
use Shopware\Core\Migration\V6_4\Migration1642732351AddAppFlowActionId;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1621845357AddFlow
 */
class Migration1621845357AddFlowTest extends TestCase
{
    public function testTablesArePresent(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        // needed to be dropped because of FK constraints
        $connection->executeStatement('DROP TABLE IF EXISTS `flow_sequence`');
        $connection->executeStatement('DROP TABLE IF EXISTS `flow`');

        $migration = new Migration1621845357AddFlow();
        $migration->update($connection);

        $flowColumns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM flow'), 'Field');

        static::assertContains('id', $flowColumns);
        static::assertContains('name', $flowColumns);
        static::assertContains('description', $flowColumns);
        static::assertContains('event_name', $flowColumns);
        static::assertContains('priority', $flowColumns);
        static::assertContains('active', $flowColumns);
        static::assertContains('payload', $flowColumns);
        static::assertContains('invalid', $flowColumns);
        static::assertContains('custom_fields', $flowColumns);
        static::assertContains('created_at', $flowColumns);
        static::assertContains('updated_at', $flowColumns);

        // Recreate dropped tables
        $migration = new Migration1621845370AddFlowSequence();
        $migration->update($connection);
        // recreate latest DB state
        $migration = new Migration1642732351AddAppFlowActionId();
        $migration->update($connection);
    }
}
