<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginRoute::class)]
class LoginRouteTest extends TestCase
{
    public function testNormalizesLimiterKey(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')->with(RateLimiter::LOGIN_ROUTE, 'user@example.com-10.0.0.1');
        $rateLimiter->expects($this->once())->method('reset')->with(RateLimiter::LOGIN_ROUTE, 'user@example.com-10.0.0.1');

        $accountService = $this->createMock(AccountService::class);
        $accountService->method('loginByCredentials')->willReturn('token');
        $route = new LoginRoute($accountService, $requestStack, $rateLimiter);

        $route->login(new RequestDataBag(['email' => " \u{00A0}User@Example.COM\u{3000}  ", 'password' => 'password']), $this->createMock(SalesChannelContext::class));
    }
}
