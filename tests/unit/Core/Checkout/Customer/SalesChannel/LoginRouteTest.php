<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(LoginRoute::class)]
class LoginRouteTest extends TestCase
{
    public function testRateLimiterIsCalledWithCorrectKeys(): void
    {
        $email = 'Test@Example.COM';
        $ip = '192.168.0.1';
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;

        $ensureAcceptedCalls = [];
        $ensureIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use (&$ensureAcceptedCalls): void {
                $ensureAcceptedCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$ensureIfConfiguredCalls): void {
                $ensureIfConfiguredCalls[] = [$route, $key];
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $route = new LoginRoute($accountService, $requestStack, $rateLimiter);
        $route->login(new RequestDataBag(['email' => $email, 'password' => 'shopware']), $this->createMock(SalesChannelContext::class));

        static::assertSame([[RateLimiter::LOGIN_ROUTE, $expectedCombinedKey]], $ensureAcceptedCalls);
        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey],
            [RateLimiter::LOGIN_CLIENT, $ip],
        ], $ensureIfConfiguredCalls);
    }

    public function testRateLimitersAreResetOnSuccessfulLogin(): void
    {
        $email = 'user@example.com';
        $ip = '10.0.0.1';
        $combinedKey = $email . '-' . $ip;

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('reset')
            ->with(RateLimiter::LOGIN_ROUTE, $combinedKey);

        $resetIfConfiguredCalls = [];
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $route = new LoginRoute($accountService, $requestStack, $rateLimiter);
        $route->login(new RequestDataBag(['email' => $email, 'password' => 'shopware']), $this->createMock(SalesChannelContext::class));

        static::assertSame([
            [RateLimiter::LOGIN_CLIENT, $ip],
            [RateLimiter::LOGIN_USER, $email],
        ], $resetIfConfiguredCalls);
    }

    public function testRateLimitThrowsCustomerAuthThrottled(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));

        $route = new LoginRoute(
            $this->createMock(AccountService::class),
            $requestStack,
            $rateLimiter,
        );

        $this->expectException(CustomerException::class);

        $route->login(
            new RequestDataBag(['email' => 'test@example.com', 'password' => 'pw']),
            $this->createMock(SalesChannelContext::class),
        );
    }

    public function testNoRateLimitingWithoutMainRequest(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $rateLimiter->expects($this->never())->method('ensureAcceptedIfConfigured');
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $route = new LoginRoute($accountService, new RequestStack(), $rateLimiter);

        $response = $route->login(
            new RequestDataBag(['email' => 'test@example.com', 'password' => 'pw']),
            $this->createMock(SalesChannelContext::class),
        );

        static::assertSame('test-token', $response->getToken());
    }
}
