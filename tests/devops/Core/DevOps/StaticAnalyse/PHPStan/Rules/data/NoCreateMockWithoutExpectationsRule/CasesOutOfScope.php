<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Example\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface OutOfScopeDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * Lives in a unit-test namespace (per TestRuleHelper) that is NOT in the rule's enabled allowlist — the
 * migration test suite is not swept — so even a clear stub must NOT be flagged.
 */
class CasesOutOfScope extends TestCase
{
    public function testStubIsNotFlaggedOutsideEnabledNamespaces(): void
    {
        $dependency = $this->createMock(OutOfScopeDependency::class);
        $dependency->method('value')->willReturn('stub');

        static::assertSame('stub', $dependency->value());
    }
}

/**
 * @internal
 *
 * Same namespace gate for the PHPUnit 13 chain checks: a `->with()` without `->expects()` and a
 * `->atLeast(0)` outside the enabled namespaces must NOT be flagged either.
 */
class ChainCasesOutOfScope extends TestCase
{
    public function testChainsAreNotFlaggedOutsideEnabledNamespaces(): void
    {
        $dependency = $this->getMockBuilder(OutOfScopeDependency::class)->getMock();
        $dependency->method('value')->with()->willReturn('a');
        $dependency->expects($this->atLeast(0))->method('value');

        static::assertSame('a', $dependency->value());
    }
}
