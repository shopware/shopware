<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCounter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueryCounter::class)]
class QueryCounterTest extends TestCase
{
    public function testStartsAtZero(): void
    {
        static::assertSame(0, (new QueryCounter())->count());
    }

    public function testIncrementCountsUp(): void
    {
        $counter = new QueryCounter();
        $counter->increment();
        $counter->increment();

        static::assertSame(2, $counter->count());
    }

    public function testResetReturnsCountAndZeroesIt(): void
    {
        $counter = new QueryCounter();
        $counter->increment();
        $counter->increment();

        static::assertSame(2, $counter->reset());
        static::assertSame(0, $counter->count());
    }
}
