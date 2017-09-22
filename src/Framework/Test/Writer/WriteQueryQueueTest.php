<?php declare(strict_types=1);

namespace Shopware\Framework\Tests\Writer;

use PHPUnit\Framework\TestCase;
use Shopware\Framework\Write\Query\WriteQueryQueue;

class WriteQueryQueueTest extends TestCase
{
    public function test_set_and_update_order_integrate_the_new_values()
    {
        $queue = new WriteQueryQueue();
        $queue->setOrder('Foo', 'A', 'B', 'C');

        $queue->updateOrder('B', 'B-1', 'B', 'B1');

        self::assertEquals(['A', 'B-1', 'B', 'B1', 'C'], $queue->getOrder());
    }

}