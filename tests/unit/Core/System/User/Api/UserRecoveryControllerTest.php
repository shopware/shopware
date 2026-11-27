<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserRecoveryController::class)]
class UserRecoveryControllerTest extends TestCase
{
    public function testUpdateUserPasswordResetsAllRateLimiters(): void
    {
        $username = " \u{00A0}Admin\u{3000}  ";
        $email = " \u{00A0}Admin@Example.com\u{3000}  ";
        $ip = '10.0.0.1';
        $expectedUsernameKey = 'admin';
        $expectedEmailKey = 'admin@example.com';

        $user = new UserEntity();
        $user->setUsername($username);
        $user->setEmail($email);

        $userRecoveryService = $this->createMock(UserRecoveryService::class);
        $userRecoveryService->expects($this->once())->method('getUserByHash')->willReturn($user);
        $userRecoveryService->expects($this->once())->method('updatePassword')->willReturn(true);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(3))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $controller = new UserRecoveryController($userRecoveryService, $rateLimiter);

        $request = new Request(server: ['REMOTE_ADDR' => $ip]);
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'newPassword123');
        $request->request->set('passwordConfirm', 'newPassword123');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertSame([
            [RateLimiter::OAUTH, $expectedUsernameKey . '-' . $ip],
            [RateLimiter::USER_RECOVERY, $expectedEmailKey . '-' . $ip],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::OAUTH_USER, $expectedUsernameKey],
            [RateLimiter::OAUTH_CLIENT, $ip],
            [RateLimiter::USER_RECOVERY_USER, $expectedEmailKey],
        ], $resetIfConfiguredCalls);
    }

    #[DataProvider('clientIpProvider')]
    public function testCreateUserRecoveryRateLimitersAreCalledWithCorrectKeys(string $ip, string $expectedClientKey): void
    {
        $calls = [];
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });
        $rateLimiter->expects($this->once())
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $controller = new UserRecoveryController(static::createStub(UserRecoveryService::class), $rateLimiter);

        $request = new Request(server: ['REMOTE_ADDR' => $ip]);
        $request->request->set('email', " \u{00A0}Admin@Example.com\u{3000}  ");

        $controller->createUserRecovery($request, Context::createDefaultContext());

        static::assertSame([
            [RateLimiter::USER_RECOVERY_CLIENT, $expectedClientKey],
            [RateLimiter::USER_RECOVERY, 'admin@example.com-' . $ip],
            [RateLimiter::USER_RECOVERY_USER, 'admin@example.com'],
        ], $calls);
    }

    public static function clientIpProvider(): \Generator
    {
        yield 'IPv4 is kept' => ['10.0.0.1', '10.0.0.1'];
        yield 'IPv6 is grouped by /64' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::'];
        yield 'IPv4 mapped in IPv6 is kept' => ['::ffff:192.0.2.10', '::ffff:192.0.2.10'];
    }

    public function testCreateUserRecoveryIsSilentNoOpWhenDisabled(): void
    {
        $userRecoveryService = $this->createMock(UserRecoveryService::class);
        $userRecoveryService->expects($this->never())->method('generateUserRecovery');

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $rateLimiter->expects($this->never())->method('ensureAcceptedIfConfigured');

        $controller = new UserRecoveryController($userRecoveryService, $rateLimiter, false);

        $request = new Request();
        $request->request->set('email', 'admin@example.com');

        $response = $controller->createUserRecovery($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCheckUserRecoveryIsRejectedWhenDisabled(): void
    {
        $userRecoveryService = $this->createMock(UserRecoveryService::class);
        $userRecoveryService->expects($this->never())->method('checkHash');

        $controller = new UserRecoveryController(
            $userRecoveryService,
            static::createStub(RateLimiter::class),
            false,
        );

        $request = new Request();
        $request->query->set('hash', 'some-hash');

        $response = $controller->checkUserRecovery($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUpdateUserPasswordIsRejectedWhenDisabled(): void
    {
        $userRecoveryService = $this->createMock(UserRecoveryService::class);
        $userRecoveryService->expects($this->never())->method('getUserByHash');
        $userRecoveryService->expects($this->never())->method('updatePassword');

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new UserRecoveryController($userRecoveryService, $rateLimiter, false);

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'newPassword123');
        $request->request->set('passwordConfirm', 'newPassword123');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRateLimitersNotResetWhenPasswordsDontMatch(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new UserRecoveryController(
            static::createStub(UserRecoveryService::class),
            $rateLimiter,
        );

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'pass1');
        $request->request->set('passwordConfirm', 'pass2');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRateLimitersNotResetWhenUserNotFound(): void
    {
        $userRecoveryService = $this->createMock(UserRecoveryService::class);
        $userRecoveryService->expects($this->once())->method('getUserByHash')->willReturn(null);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new UserRecoveryController($userRecoveryService, $rateLimiter);

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'pass');
        $request->request->set('passwordConfirm', 'pass');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
