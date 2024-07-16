<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\DocumentGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentGenerator\Counter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Counter::class)]
class CounterTest extends TestCase
{
    public function testCounter(): void
    {
        $counter = new Counter();

        static::assertSame(0, $counter->getCounter());

        $counter->increment();

        static::assertSame(1, $counter->getCounter());

        $counter->increment();
        $counter->increment();
        $counter->increment();

        static::assertSame(4, $counter->getCounter());
    }
}
