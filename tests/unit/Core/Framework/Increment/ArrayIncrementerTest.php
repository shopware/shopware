<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Increment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\ArrayIncrementer;

/**
 * @internal
 */
#[CoversClass(ArrayIncrementer::class)]
class ArrayIncrementerTest extends TestCase
{
    private ArrayIncrementer $arrayIncrementer;

    protected function setUp(): void
    {
        $this->arrayIncrementer = new ArrayIncrementer();
        $this->arrayIncrementer->setPool('user-activity-pool');
    }

    public function testDecrementDoesNotCreate(): void
    {
        $this->arrayIncrementer->decrement('test', 'test');
        static::assertEmpty($this->arrayIncrementer->list('test'));
    }

    public function testIncrement(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertSame(1, $list['sw.product.index']['count']);

        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(2, $list['sw.product.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertSame(2, $list['sw.product.index']['count']);

        $this->arrayIncrementer->decrement('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['sw.product.index']['count']);
    }

    public function testList(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(2, array_values($list)[0]['count']);
        static::assertSame('sw.product.index', array_values($list)[0]['key']);
        static::assertSame(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->arrayIncrementer->increment('test-user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.order.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(3, array_values($list)[0]['count']);
        static::assertSame('sw.order.index', array_values($list)[0]['key']);
        static::assertSame(2, array_values($list)[1]['count']);

        static::assertEmpty($this->arrayIncrementer->list('test2'));
    }

    public function testReset(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->reset('test-user-1');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(0, $list['sw.product.index']['count']);

        $this->arrayIncrementer->increment('test-user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['sw.product.index']['count']);
        static::assertSame(1, $list['sw.order.index']['count']);

        $this->arrayIncrementer->reset('test-user-1', 'sw.order.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['sw.product.index']['count']);
        static::assertSame(0, $list['sw.order.index']['count']);
    }

    public function testDeleteClusterWithKeys(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.create');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.update');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->delete('test-user-1', ['sw.product.index', 'sw.product.create']);

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame([
            'sw.product.update' => [
                'key' => 'sw.product.update',
                'cluster' => 'test-user-1',
                'pool' => 'user-activity-pool',
                'count' => 1,
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->delete('test-user-1');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertEmpty($list);
    }
}
