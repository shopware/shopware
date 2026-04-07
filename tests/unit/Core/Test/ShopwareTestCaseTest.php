<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\ShopwareTestCase;

/**
 * @internal
 */
#[CoversClass(ShopwareTestCase::class)]
class ShopwareTestCaseTest extends TestCase
{
    public function testArrayEmptyAndNotEmpty(): void
    {
        ShopwareTestCase::assertStrictEmpty([]);
        ShopwareTestCase::assertNotStrictEmpty([1]);
    }

    public function testCountableEmpty(): void
    {
        $c = new class implements \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        ShopwareTestCase::assertStrictEmpty($c);
    }

    public function testTraversableEmptyAndNotEmpty(): void
    {
        $itEmpty = new \ArrayIterator([]);
        $itNotEmpty = new \ArrayIterator([1]);

        ShopwareTestCase::assertStrictEmpty($itEmpty);
        ShopwareTestCase::assertNotStrictEmpty($itNotEmpty);
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

        ShopwareTestCase::assertStrictEmpty($genEmpty);
        ShopwareTestCase::assertNotStrictEmpty($genNotEmpty);
    }

    public function testPrimitivesAreNotConsideredEmpty(): void
    {
        // By strict definition used here, primitives are not treated as "empty":
        // empty string, numeric zero, false and null are NOT strictly empty.
        ShopwareTestCase::assertNotStrictEmpty('');
        ShopwareTestCase::assertNotStrictEmpty(0);
        ShopwareTestCase::assertNotStrictEmpty(false);
        ShopwareTestCase::assertNotStrictEmpty(null);
    }
}
