<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1629700169AddEnqueueIndex;

class Migration1629700169AddEnqueueIndexTest extends TestCase
{
    use KernelTestBehaviour;

    public function testIndexExists(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        $indices = $c->getSchemaManager()->listTableIndexes('enqueue');

        static::assertArrayHasKey('delivery_id', $indices);
    }

    public function testMultipleExecutions(): void
    {
        $c = $this->getContainer()->get(Connection::class);

        for ($i = 0; $i < 2; ++$i) {
            $indices = $c->getSchemaManager()->listTableIndexes('enqueue');
            static::assertArrayHasKey('delivery_id', $indices);

            $migration = new Migration1629700169AddEnqueueIndex();
            $migration->update($c);
        }

        static::assertTrue($i === 2);
    }
}
