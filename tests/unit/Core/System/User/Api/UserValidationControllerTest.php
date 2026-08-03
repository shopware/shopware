<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\Api\UserValidationController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserValidationController::class)]
class UserValidationControllerTest extends TestCase
{
    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testValidationRoutesRequireUserReadPrivilege(string $routeName, array $expectedPrivileges): void
    {
        $route = (new AttributeRouteControllerLoader())->load(UserValidationController::class)->get($routeName);

        static::assertNotNull($route);
        static::assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'email uniqueness validation' => ['api.action.check-email-unique', ['user:read']];
        yield 'username uniqueness validation' => ['api.action.check-username-unique', ['user:read']];
    }
}
