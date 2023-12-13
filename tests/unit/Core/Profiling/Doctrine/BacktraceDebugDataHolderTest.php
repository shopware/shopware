<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;

/**
 * @internal
 */
#[CoversClass(BacktraceDebugDataHolder::class)]
class BacktraceDebugDataHolderTest extends TestCase
{
    public function testAddAndRetrieveData(): void
    {
        $sut = new BacktraceDebugDataHolder([]);
        $sut->addQuery('myconn', new Query('SELECT * FROM product'));

        $data = $sut->getData();
        static::assertCount(1, $data['myconn'] ?? []);
        $current = $data['myconn'][0];

        static::assertSame(0, strpos($current['sql'], 'SELECT * FROM product'));
        static::assertSame([], $current['params']);
        static::assertSame([], $current['types']);
    }

    public function testReset(): void
    {
        $sut = new BacktraceDebugDataHolder([]);
        $sut->addQuery('myconn', new Query('SELECT * FROM product'));

        static::assertCount(1, $sut->getData()['myconn'] ?? []);
        $sut->reset();
        static::assertCount(0, $sut->getData()['myconn'] ?? []);
    }

    public function testBacktracesEnabled(): void
    {
        $sut = new BacktraceDebugDataHolder(['myconn2']);
        $this->funcForBacktraceGeneration($sut);

        $data = $sut->getData();
        static::assertCount(1, $data['myconn1'] ?? []);
        static::assertCount(1, $data['myconn2'] ?? []);
        $currentConn1 = $data['myconn1'][0];
        $currentConn2 = $data['myconn2'][0];

        static::assertArrayNotHasKey('backtrace', $currentConn1);
        static::assertArrayHasKey('backtrace', $currentConn2);
        static::assertGreaterThan(0, \count($currentConn2['backtrace']));

        $lastCall = $currentConn2['backtrace'][0];
        static::assertSame(self::class, $lastCall['class'] ?? '');
        static::assertSame(__FUNCTION__, $lastCall['function'] ?? '');
    }

    private function funcForBacktraceGeneration(BacktraceDebugDataHolder $sut): void
    {
        $sut->addQuery('myconn1', new Query('SELECT * FROM product'));
        $sut->addQuery('myconn2', new Query('SELECT * FROM car'));
    }
}
