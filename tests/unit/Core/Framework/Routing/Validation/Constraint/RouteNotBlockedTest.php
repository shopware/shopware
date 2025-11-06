<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked;

/**
 * @internal
 */
#[CoversClass(RouteNotBlocked::class)]
class RouteNotBlockedTest extends TestCase
{
    public function testDefaultMessage(): void
    {
        $constraint = new RouteNotBlocked();

        static::assertSame('FRAMEWORK__ROUTE_BLOCKED_MESSAGE', $constraint->getMessage());
    }
}
