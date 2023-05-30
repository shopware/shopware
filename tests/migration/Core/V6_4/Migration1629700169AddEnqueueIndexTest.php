<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1568645037AddEnqueueDbal;
use Shopware\Core\Migration\V6_4\Migration1629700169AddEnqueueIndex;
use Shopware\Core\Migration\V6_5\Migration1669125399DropEnqueueTable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1629700169AddEnqueueIndex
 */
class Migration1629700169AddEnqueueIndexTest extends TestCase
{
    public function testIndexExists(): void
    {
        $c = KernelLifecycleManager::getConnection();

        $createTable = new Migration1568645037AddEnqueueDbal();
        $createTable->update($c);

        $addIndex = new Migration1629700169AddEnqueueIndex();
        $addIndex->update($c);

        $indices = $c->createSchemaManager()->listTableIndexes('enqueue');

        static::assertArrayHasKey('delivery_id', $indices);

        $dropTable = new Migration1669125399DropEnqueueTable();
        $dropTable->updateDestructive($c);
    }

    public function testMultipleExecutions(): void
    {
        $c = KernelLifecycleManager::getConnection();

        $createTable = new Migration1568645037AddEnqueueDbal();
        $createTable->update($c);

        $addIndex = new Migration1629700169AddEnqueueIndex();
        $addIndex->update($c);

        for ($i = 0; $i < 2; ++$i) {
            $indices = $c->createSchemaManager()->listTableIndexes('enqueue');
            static::assertArrayHasKey('delivery_id', $indices);

            $migration = new Migration1629700169AddEnqueueIndex();
            $migration->update($c);
        }

        static::assertTrue($i === 2);

        $dropTable = new Migration1669125399DropEnqueueTable();
        $dropTable->updateDestructive($c);
    }
}
