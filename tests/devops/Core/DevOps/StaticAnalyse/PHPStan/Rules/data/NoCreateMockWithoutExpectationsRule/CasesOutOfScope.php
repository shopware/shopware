<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Example\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface OutOfScopeDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * Lives in a namespace that is NOT yet in the rule's enabled allowlist, so even a clear stub must NOT be
 * flagged — the rule only enforces in already-swept domains.
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
