<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\Api\NumberRangeController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeController::class)]
class NumberRangeControllerTest extends TestCase
{
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresNumberRangeReadPrivilege(string $routeName): void
    {
        $route = (new AttributeRouteControllerLoader())->load(NumberRangeController::class)->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, NumberRangeController::class)
        );
        static::assertSame(['number_range:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'reserving a number consumes the range state' => ['api.action.number-range.reserve'];
        yield 'previewing a pattern exposes the range configuration' => ['api.action.number-range.preview-pattern'];
    }
}
