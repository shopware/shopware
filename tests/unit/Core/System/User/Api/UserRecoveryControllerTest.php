<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserRecoveryController::class)]
class UserRecoveryControllerTest extends TestCase
{
    public function testResetsNormalizedLoginCounter(): void
    {
        $user = new UserEntity();
        $user->setUsername(" \u{00A0}Admin\u{3000}  ");
        $user->setEmail('admin@example.com');
        $service = $this->createMock(UserRecoveryService::class);
        $service->method('getUserByHash')->willReturn($user);
        $service->method('updatePassword')->willReturn(true);
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))->method('reset')->willReturnCallback(static function (string $route, string $key): void {
            static::assertSame($route === RateLimiter::OAUTH ? 'admin-10.0.0.1' : 'admin@example.com-10.0.0.1', $key);
        });
        $controller = new UserRecoveryController($service, $rateLimiter);

        $controller->updateUserPassword(new Request(request: ['hash' => 'hash', 'password' => 'password', 'passwordConfirm' => 'password'], server: ['REMOTE_ADDR' => '10.0.0.1']), Context::createDefaultContext());
    }
}
