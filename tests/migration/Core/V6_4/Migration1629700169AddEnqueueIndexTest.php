<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1629700169AddEnqueueIndex;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1629700169AddEnqueueIndex
 */
class Migration1629700169AddEnqueueIndexTest extends TestCase
{
    public function testIndexExists(): void
    {
        $c = KernelLifecycleManager::getConnection();
        $indices = $c->getSchemaManager()->listTableIndexes('enqueue');

        static::assertArrayHasKey('delivery_id', $indices);
    }

    public function testMultipleExecutions(): void
    {
        $c = KernelLifecycleManager::getConnection();

        for ($i = 0; $i < 2; ++$i) {
            $indices = $c->getSchemaManager()->listTableIndexes('enqueue');
            static::assertArrayHasKey('delivery_id', $indices);

            $migration = new Migration1629700169AddEnqueueIndex();
            $migration->update($c);
        }

        static::assertTrue($i === 2);
    }
}
