<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\StrictEmptyHelper;

/**
 * @internal
 */
#[CoversClass(StrictEmptyHelper::class)]
class StrictIsEmptyTest extends TestCase
{
    private StrictEmptyHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new StrictEmptyHelper();
    }

    public function testArrayEmptyAndNotEmpty(): void
    {
        $this->helper->assertStrictEmpty([]);
        $this->helper->assertNotStrictEmpty([1]);
    }

    public function testCountableEmpty(): void
    {
        $c = new class implements \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        $this->helper->assertStrictEmpty($c);
    }

    public function testTraversableEmptyAndNotEmpty(): void
    {
        $itEmpty = new \ArrayIterator([]);
        $itNotEmpty = new \ArrayIterator([1]);

        $this->helper->assertStrictEmpty($itEmpty);
        $this->helper->assertNotStrictEmpty($itNotEmpty);
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

        $this->helper->assertStrictEmpty($genEmpty);
        $this->helper->assertNotStrictEmpty($genNotEmpty);
    }

    public function testPrimitivesAreNotConsideredEmpty(): void
    {
        // By strict definition used here, primitives are not treated as "empty":
        // empty string, numeric zero, false and null are NOT strictly empty.
        $this->helper->assertNotStrictEmpty('');
        $this->helper->assertNotStrictEmpty(0);
        $this->helper->assertNotStrictEmpty(false);
        $this->helper->assertNotStrictEmpty(null);
    }
}
