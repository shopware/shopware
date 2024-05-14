<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\LoginAsCustomerTokenGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginAsCustomerRoute;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginAsCustomerRoute::class)]
class LoginAsCustomerRouteTest extends TestCase
{
    public function testLoginAsCustomer(): void
    {
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setEmail('customer@example.com');
        $customerEntity->setGuest(false);

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $route = new LoginAsCustomerRoute(
            $dispatcher,
            $customerRepository,
            $this->createMock(CartRestorer::class),
            $this->createMock(LoginAsCustomerTokenGenerator::class)
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $dataBag = new RequestDataBag([
            LoginAsCustomerRoute::CUSTOMER_ID => 'customer-1',
            LoginAsCustomerRoute::TOKEN => 'token-1',
        ]);

        $response = $route->loginAsCustomer($dataBag, $salesChannelContext);

        self::assertInstanceOf(ContextTokenResponse::class, $response);
    }
}
