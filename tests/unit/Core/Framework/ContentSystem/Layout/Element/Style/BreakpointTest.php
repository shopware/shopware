<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Breakpoint::class)]
class BreakpointTest extends TestCase
{
    #[TestDox('returns the canonical breakpoint keys in declaration order')]
    public function testValuesReturnsCanonicalKeys(): void
    {
        static::assertSame(['xs', 'sm', 'md', 'lg', 'xl', 'xxl'], Breakpoint::values());
    }

    public function testFallbackMapExposesTheOrderedCandidatesForEveryBreakpoint(): void
    {
        static::assertSame([
            'xs' => ['sm', 'lg'],
            'sm' => ['lg'],
            'md' => ['lg'],
            'lg' => [],
            'xl' => ['lg'],
            'xxl' => ['xl', 'lg'],
        ], Breakpoint::fallbackMap());
    }

    public function testResolveUsesTheFirstPresentValue(): void
    {
        static::assertSame(0, Breakpoint::Xs->resolve(['xs' => 0, 'sm' => 1, 'lg' => 2]));
        static::assertFalse(Breakpoint::Xs->resolve(['sm' => false, 'lg' => true]));
        static::assertSame(2, Breakpoint::Xs->resolve(['md' => 1, 'lg' => 2]));
        static::assertSame(2, Breakpoint::Md->resolve(['sm' => 1, 'lg' => 2]));
        static::assertNull(Breakpoint::Lg->resolve(['sm' => 1]));
    }
}
