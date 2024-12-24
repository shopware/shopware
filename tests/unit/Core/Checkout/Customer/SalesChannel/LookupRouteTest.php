<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LookupRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LookupRoute::class)]
class LookupRouteTest extends TestCase
{
    public function testInvalidSystemConfig(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.loginRegistration.allowAccountLookup' => false,
        ]);

        $lookupRoute = new LookupRoute(
            $this->createMock(AccountService::class),
            new RequestStack(),
            $this->createMock(RateLimiter::class),
            $systemConfigService,
        );

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Customer account lookup is disabled');
        $lookupRoute->lookup(new RequestDataBag(), $this->createMock(SalesChannelContext::class));
    }

    public function testLookup(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.loginRegistration.allowAccountLookup' => true,
        ]);

        $context = Generator::createSalesChannelContext();

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::once())
            ->method('customerExists')
            ->with('foo@bar.com', $context)
            ->willReturn(true);

        $lookupRoute = new LookupRoute(
            $accountService,
            new RequestStack(),
            $this->createMock(RateLimiter::class),
            $systemConfigService,
        );

        static::assertEquals((new SuccessResponse(true))->getObject(), $lookupRoute->lookup(new RequestDataBag(['email' => 'foo@bar.com']), $context)->getObject());
    }

    public function testRateLimit(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.loginRegistration.allowAccountLookup' => true,
        ]);

        $context = Generator::createSalesChannelContext();

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::never())->method('customerExists');

        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1']));

        $exception = new RateLimitExceededException(time() + 5);
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects(static::once())
            ->method('ensureAccepted')
            ->with(RateLimiter::LOOKUP_ROUTE, '127.0.0.1')
            ->willThrowException($exception);

        $lookupRoute = new LookupRoute(
            $accountService,
            $requestStack,
            $rateLimiter,
            $systemConfigService,
        );

        $this->expectException(CustomerAuthThrottledException::class);
        $this->expectExceptionMessage(\sprintf('Customer auth throttled for %s seconds.', $exception->getWaitTime()));
        $lookupRoute->lookup(new RequestDataBag(['email' => 'foo@bar.com']), $context);
    }
}
