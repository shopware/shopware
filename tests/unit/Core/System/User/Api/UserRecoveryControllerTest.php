<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UserRecoveryController::class)]
class UserRecoveryControllerTest extends TestCase
{
    public function testUpdateUserPasswordResetsAllRateLimiters(): void
    {
        $username = 'Admin';
        $email = 'admin@example.com';
        $ip = '10.0.0.1';
        $expectedUsernameKey = strtolower($username);

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
        $rateLimiter->expects($this->exactly(2))
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
            [RateLimiter::USER_RECOVERY, strtolower($email) . '-' . $ip],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::OAUTH_USER, $expectedUsernameKey],
            [RateLimiter::OAUTH_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }

    public function testRateLimitersNotResetWhenPasswordsDontMatch(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new UserRecoveryController(
            $this->createMock(UserRecoveryService::class),
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
