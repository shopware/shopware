<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Assert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Assert\StrictEmpty;

/**
 * @internal
 */
#[CoversClass(StrictEmpty::class)]
class StrictEmptyTest extends TestCase
{
    public function testArrayEmptyAndNotEmpty(): void
    {
        StrictEmpty::assertEmpty([]);
        StrictEmpty::assertNotEmpty([1]);
    }

    public function testCountableEmpty(): void
    {
        $c = new class implements \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        StrictEmpty::assertEmpty($c);
    }

    public function testTraversableEmptyAndNotEmpty(): void
    {
        $itEmpty = new \ArrayIterator([]);
        $itNotEmpty = new \ArrayIterator([1]);

        StrictEmpty::assertEmpty($itEmpty);
        StrictEmpty::assertNotEmpty($itNotEmpty);
    }

    public function testGeneratorConsumedAndEmpty(): void
    {
        $genEmpty = (function (): \Generator {
            // explicit empty generator using yield from [] to satisfy static analyzers
            yield from [];
        })();

        $genNotEmpty = (function (): \Generator {
            yield 1;
        })();

        StrictEmpty::assertEmpty($genEmpty);
        StrictEmpty::assertNotEmpty($genNotEmpty);
    }

    public function testPrimitivesAreNotConsideredEmpty(): void
    {
        // By strict definition used here, primitives are not treated as "empty":
        // empty string, numeric zero, false and null are NOT strictly empty.
        StrictEmpty::assertNotEmpty('');
        StrictEmpty::assertNotEmpty(0);
        StrictEmpty::assertNotEmpty(false);
        StrictEmpty::assertNotEmpty(null);
    }
}
