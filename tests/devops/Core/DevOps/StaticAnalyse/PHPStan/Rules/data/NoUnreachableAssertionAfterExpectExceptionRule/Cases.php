<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnreachableAssertionAfterExpectExceptionRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class UnreachableAssertions extends TestCase
{
    public function testTrailingAssertNeverRuns(): void
    {
        $items = new \ArrayObject(['a']);

        $this->expectException(\RuntimeException::class);

        self::throwingCall($items);

        static::assertCount(1, $items);
    }

    public function testDeadTailAfterSecondActPhase(): void
    {
        $items = new \ArrayObject(['a']);

        static::expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        self::throwingCall($items);

        static::assertCount(1, $items);

        $items->append('b');

        static::assertCount(2, $items);
    }

    public static function throwingCall(\ArrayObject $items): void
    {
        throw new \RuntimeException('boom: ' . \count($items));
    }
}

/**
 * @internal
 */
class CompliantShapes extends TestCase
{
    public function testAssertionBeforeActRuns(): void
    {
        $items = new \ArrayObject(['a']);

        $this->expectException(\RuntimeException::class);

        static::assertCount(1, $items);

        UnreachableAssertions::throwingCall($items);
    }

    public function testTryFinallyKeepsAssertionAlive(): void
    {
        $items = new \ArrayObject(['a']);

        $this->expectException(\RuntimeException::class);

        try {
            UnreachableAssertions::throwingCall($items);
        } finally {
            static::assertCount(1, $items);
        }
    }

    public function testNoExpectException(): void
    {
        $items = new \ArrayObject(['a']);

        $items->append('b');

        static::assertCount(2, $items);
    }

    public function testAssertLikeCallOnOtherObjectIsNotAnAssertion(): void
    {
        $helper = new AssertingHelper();

        $this->expectException(\RuntimeException::class);

        UnreachableAssertions::throwingCall(new \ArrayObject());

        // dead act statement, but not an assertion: not reported by this rule
        $helper->assertState();
    }

    public function testConditionalExpectExceptionIsIgnored(): void
    {
        $items = new \ArrayObject([]);

        if ($items->count() > 0) {
            $this->expectException(\RuntimeException::class);
        }

        $items->append('a');

        static::assertCount(1, $items);
    }
}

/**
 * Not a TestCase: never reported, even with the banned shape.
 *
 * @internal
 */
class NotATestCase
{
    public function expectException(string $class): void
    {
    }

    public function run(): void
    {
        $this->expectException(\RuntimeException::class);

        UnreachableAssertions::throwingCall(new \ArrayObject());

        $this->assertSomething();
    }

    public function assertSomething(): void
    {
    }
}

/**
 * @internal
 */
class AssertingHelper
{
    public function assertState(): void
    {
    }
}
