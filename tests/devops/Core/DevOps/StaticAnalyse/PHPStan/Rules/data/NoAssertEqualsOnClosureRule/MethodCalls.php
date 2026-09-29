<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertEqualsOnClosureRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MethodCalls extends TestCase
{
    private TestCase $delegate;

    public function testRealClosureViaInstanceCallIsReported(): void
    {
        $a = static fn () => 1;
        $b = static fn () => 1;

        // Instance-style call on a TestCase receiver (not normalized to static:: by cs-fixer
        // because the receiver is a property, not $this directly).
        $this->delegate->assertEquals($a, $b);
    }

    public function testClosureComparedViaNonTestCaseReceiverIsNotReported(): void
    {
        // The rule only fires when the receiver is a TestCase instance; an unrelated
        // object that happens to expose an assertEquals() method must be left alone.
        $a = static fn () => 1;

        (new NotATestCase())->assertEquals($a, $a);
    }
}

/**
 * @internal
 */
class NotATestCase
{
    public function assertEquals(mixed $expected, mixed $actual): void
    {
    }
}
