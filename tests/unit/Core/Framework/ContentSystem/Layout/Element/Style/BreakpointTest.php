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
}
