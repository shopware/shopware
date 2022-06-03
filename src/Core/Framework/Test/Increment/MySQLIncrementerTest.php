<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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
        $this->mysqlIncrementer = new MySQLIncrementer($this->getContainer()->get(Connection::class));
        $this->mysqlIncrementer->setPool('user-activity-pool');
    }

    public function testIncrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertEquals(1, $list['sw.product.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(2, $list['sw.product.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['sw.product.index']);
        static::assertEquals(2, $list['sw.product.index']['count']);

        $this->mysqlIncrementer->decrement('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(1, $list['sw.product.index']['count']);
    }

    public function testList(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(2, array_values($list)[0]['count']);
        static::assertEquals('sw.product.index', array_values($list)[0]['key']);
        static::assertEquals(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(3, array_values($list)[0]['count']);
        static::assertEquals('sw.order.index', array_values($list)[0]['key']);
        static::assertEquals(2, array_values($list)[1]['count']);
    }

    public function testReset(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->reset('test-user-1');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(0, $list['sw.product.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-user-1', 'sw.product.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(1, $list['sw.product.index']['count']);
        static::assertEquals(1, $list['sw.order.index']['count']);

        $this->mysqlIncrementer->reset('test-user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals(1, $list['sw.product.index']['count']);
        static::assertEquals(0, $list['sw.order.index']['count']);
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->mysqlIncrementer->getDecorated();
    }
}
