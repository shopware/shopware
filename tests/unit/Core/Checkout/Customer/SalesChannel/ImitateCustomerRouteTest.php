<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerRoute::class)]
class ImitateCustomerRouteTest extends TestCase
{
    public function testImitateCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        $imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator('testAppSecret');

        $token = $imitateCustomerTokenGenerator->generate(
            TestDefaults::SALES_CHANNEL,
            $customerId,
            $userId
        );

        $accountService = $this->createMock(AccountService::class);
        $accountService->method('loginById')->willReturn('newToken');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            $this->createMock(LogoutRoute::class),
            $this->createMock(SalesChannelContextFactory::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidator::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
            ImitateCustomerRoute::CUSTOMER_ID => $customerId,
            ImitateCustomerRoute::USER_ID => $userId,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertEquals('newToken', $response->getToken());
    }
}
