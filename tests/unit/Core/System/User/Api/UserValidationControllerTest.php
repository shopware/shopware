<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\Api\UserValidationController;
use Shopware\Core\System\User\Service\UserValidationService;
use Shopware\Core\System\User\UserException;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;

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
     * @param array<string, string> $payload
     */
    #[DataProvider('missingEmailParameterProvider')]
    public function testEmailValidationRejectsMissingParameters(array $payload, string $missingParameter): void
    {
        $controller = new UserValidationController(static::createStub(UserValidationService::class));

        static::expectExceptionObject(UserException::missingRequestParameter($missingParameter));

        $controller->isEmailUnique(
            Request::create('/', Request::METHOD_POST, $payload),
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('missingUsernameParameterProvider')]
    public function testUsernameValidationRejectsMissingParameters(array $payload, string $missingParameter): void
    {
        $controller = new UserValidationController(static::createStub(UserValidationService::class));

        static::expectExceptionObject(UserException::missingRequestParameter($missingParameter));

        $controller->isUsernameUnique(
            Request::create('/', Request::METHOD_POST, $payload),
            Context::createDefaultContext(),
        );
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'email uniqueness validation' => ['api.action.check-email-unique', ['user:read']];
        yield 'username uniqueness validation' => ['api.action.check-username-unique', ['user:read']];
    }

    /**
     * @return \Generator<string, array{0: array<string, string>, 1: string}>
     */
    public static function missingEmailParameterProvider(): \Generator
    {
        yield 'email is missing' => [['id' => 'id'], 'email'];
        yield 'email id is missing' => [['email' => 'email'], 'id'];
    }

    /**
     * @return \Generator<string, array{0: array<string, string>, 1: string}>
     */
    public static function missingUsernameParameterProvider(): \Generator
    {
        yield 'username is missing' => [['id' => 'id'], 'username'];
        yield 'username id is missing' => [['username' => 'username'], 'id'];
    }
}
