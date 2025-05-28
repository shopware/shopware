<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class MySQLIncrementerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MySQLIncrementer $mysqlIncrementer;

    protected function setUp(): void
    {
        $this->mysqlIncrementer = new MySQLIncrementer(static::getContainer()->get(Connection::class));
        $this->mysqlIncrementer->setPool('user-activity-pool');
    }

    public function testIncrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertSame('1', $list['sw.product.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('2', $list['sw.product.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertSame('2', $list['sw.product.index']['count']);

        $this->mysqlIncrementer->decrement('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('1', $list['sw.product.index']['count']);
    }

    public function testList(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('2', array_values($list)[0]['count']);
        static::assertSame('sw.product.index', array_values($list)[0]['key']);
        static::assertSame('1', array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('3', array_values($list)[0]['count']);
        static::assertSame('sw.order.index', array_values($list)[0]['key']);
        static::assertSame('2', array_values($list)[1]['count']);
    }

    public function testReset(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->reset('test-user-1');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('0', $list['sw.product.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('1', $list['sw.product.index']['count']);
        static::assertSame('1', $list['sw.order.index']['count']);

        $this->mysqlIncrementer->reset('test-user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame('1', $list['sw.product.index']['count']);
        static::assertSame('0', $list['sw.order.index']['count']);
    }

    public function testDeleteKeys(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.create');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->delete('test-user-1', ['sw.product.index']);

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals([
            'sw.product.create' => [
                'pool' => 'user-activity-pool',
                'cluster' => 'test-user-1',
                'key' => 'sw.product.create',
                'count' => '1',
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.create');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->delete('test-user-1');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEmpty($list);
    }
}
