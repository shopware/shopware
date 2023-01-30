<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1621845370AddFlowSequence;
use Shopware\Core\Migration\V6_4\Migration1642732351AddAppFlowActionId;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1621845370AddFlowSequence
 */
class Migration1621845370AddFlowSequenceTest extends TestCase
{
    public function testTablesArePresent(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS `flow_sequence`');

        $migration = new Migration1621845370AddFlowSequence();
        $migration->update($connection);

        $flowSequenceColumns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM flow_sequence'), 'Field');

        static::assertContains('id', $flowSequenceColumns);
        static::assertContains('flow_id', $flowSequenceColumns);
        static::assertContains('parent_id', $flowSequenceColumns);
        static::assertContains('rule_id', $flowSequenceColumns);
        static::assertContains('config', $flowSequenceColumns);
        static::assertContains('position', $flowSequenceColumns);
        static::assertContains('display_group', $flowSequenceColumns);
        static::assertContains('true_case', $flowSequenceColumns);
        static::assertContains('custom_fields', $flowSequenceColumns);
        static::assertContains('created_at', $flowSequenceColumns);
        static::assertContains('updated_at', $flowSequenceColumns);

        // recreate latest DB state
        $migration = new Migration1642732351AddAppFlowActionId();
        $migration->update($connection);
    }
}
