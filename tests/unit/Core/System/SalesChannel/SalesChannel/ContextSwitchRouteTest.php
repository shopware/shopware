<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextSwitchRoute::class)]
class ContextSwitchRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ContextSwitchRoute::class));

        (new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(SalesChannelContextPersister::class),
            $this->createEventDispatcher(),
            static::createStub(SalesChannelContextServiceInterface::class)
        ))->getDecorated();
    }

    public function testSwitchContextAllowsEmptyAddressIdsForAnonymousContext(): void
    {
        $token = 'test-token';
        $salesChannelId = Uuid::randomHex();
        $frameworkContext = Context::createDefaultContext();
        $salesChannelContext = $this->createSalesChannelContext($token, $salesChannelId, $frameworkContext);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->exactly(2))
            ->method('validate');

        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister
            ->expects($this->once())
            ->method('save')
            ->with(
                $token,
                [
                    SalesChannelContextService::BILLING_ADDRESS_ID => '',
                    SalesChannelContextService::SHIPPING_ADDRESS_ID => '',
                ],
                $salesChannelId,
                null
            );

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($salesChannelId, $token)))
            ->willReturn($salesChannelContext);

        $route = new ContextSwitchRoute(
            $validator,
            $contextPersister,
            $this->createEventDispatcher(),
            $contextService
        );

        $response = $route->switchContext(
            new RequestDataBag([
                SalesChannelContextService::BILLING_ADDRESS_ID => '',
                SalesChannelContextService::SHIPPING_ADDRESS_ID => '',
            ]),
            $salesChannelContext
        );

        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSwitchContextAllowsAddressIdsForCustomerContext(): void
    {
        $token = 'test-token';
        $salesChannelId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $frameworkContext = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $salesChannelContext = $this->createSalesChannelContext(
            $token,
            $salesChannelId,
            $frameworkContext,
            $customer
        );

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->exactly(2))
            ->method('validate');

        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister
            ->expects($this->once())
            ->method('save')
            ->with(
                $token,
                [
                    SalesChannelContextService::BILLING_ADDRESS_ID => $billingAddressId,
                    SalesChannelContextService::SHIPPING_ADDRESS_ID => $shippingAddressId,
                ],
                $salesChannelId,
                $customerId
            );

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($salesChannelId, $token)))
            ->willReturn($salesChannelContext);

        $route = new ContextSwitchRoute(
            $validator,
            $contextPersister,
            $this->createEventDispatcher(),
            $contextService
        );

        $response = $route->switchContext(
            new RequestDataBag([
                SalesChannelContextService::BILLING_ADDRESS_ID => $billingAddressId,
                SalesChannelContextService::SHIPPING_ADDRESS_ID => $shippingAddressId,
            ]),
            $salesChannelContext
        );

        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('nonEmptyAddressIdProvider')]
    public function testSwitchContextRejectsNonEmptyAddressIdsForAnonymousContext(array $parameters): void
    {
        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(SalesChannelContextPersister::class),
            $this->createEventDispatcher(),
            static::createStub(SalesChannelContextServiceInterface::class)
        );

        $this->expectExceptionObject(CartException::customerNotLoggedIn());

        $route->switchContext(
            new RequestDataBag($parameters),
            $this->createSalesChannelContext('test-token', Uuid::randomHex(), Context::createDefaultContext())
        );
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function nonEmptyAddressIdProvider(): iterable
    {
        yield 'billing address id' => [[SalesChannelContextService::BILLING_ADDRESS_ID => '0']];
        yield 'shipping address id' => [[SalesChannelContextService::SHIPPING_ADDRESS_ID => '0']];
    }

    private function createSalesChannelContext(
        string $token,
        string $salesChannelId,
        Context $frameworkContext,
        ?CustomerEntity $customer = null
    ): SalesChannelContext {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->method('getCustomerId')
            ->willReturn($customer?->getId());
        $salesChannelContext
            ->method('getToken')
            ->willReturn($token);
        $salesChannelContext
            ->method('getSalesChannelId')
            ->willReturn($salesChannelId);
        $salesChannelContext
            ->method('getContext')
            ->willReturn($frameworkContext);
        $salesChannelContext
            ->method('getPermissions')
            ->willReturn([]);

        return $salesChannelContext;
    }

    private function createEventDispatcher(): EventDispatcherInterface
    {
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static fn (object $event): object => $event);

        return $eventDispatcher;
    }
}
