<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertEqualsOnClosureRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class StaticCalls extends TestCase
{
    public function testRealClosureIsReported(): void
    {
        $a = static fn () => 1;
        $b = static fn () => 1;

        static::assertEquals($a, $b);
    }

    public function testNeverTypedArgumentIsNotReported(): void
    {
        // A `never`-typed operand (here from a never-returning accessor; in real
        // code typically from assertNull() + assertNotNull() narrowing on the same
        // accessor) is the bottom type and must not be mistaken for a Closure.
        static::assertEquals(new \stdClass(), $this->never());
    }

    public function testNonClosureObjectIsNotReported(): void
    {
        static::assertEquals(new \stdClass(), new \stdClass());
    }

    private function never(): never
    {
        throw new \RuntimeException('unreachable');
    }
}
