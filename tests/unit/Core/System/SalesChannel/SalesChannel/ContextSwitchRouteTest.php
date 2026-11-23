<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\ProspectiveAddress;
use Shopware\Core\Checkout\Customer\ProspectiveCustomer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\Extension\ContextSwitchRouteExtension;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
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
            $contextService,
            new ExtensionDispatcher(new EventDispatcher())
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
            $contextService,
            new ExtensionDispatcher(new EventDispatcher())
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
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
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

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag([SalesChannelContextService::LANGUAGE_ID => Uuid::randomHex()]);
        $context = Generator::generateSalesChannelContext();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('context-switch-route.switch-context.pre', static function (ContextSwitchRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->switchContext($data, $context));
    }

    public function testShippingAddressIsStoredAsProspectiveCustomerAndBecomesTheContextCountry(): void
    {
        $countryId = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $saved = $this->switchAnonymousContext(new RequestDataBag([
            'shippingAddress' => ['countryId' => $countryId, 'countryStateId' => $stateId, 'zipcode' => '48624', 'firstName' => 'not stored'],
        ]));

        static::assertSame([
            SalesChannelContextService::PROSPECTIVE_CUSTOMER => [
                'shippingAddress' => ['countryId' => $countryId, 'countryStateId' => $stateId, 'zipcode' => '48624', 'city' => null, 'street' => null],
            ],
            SalesChannelContextService::COUNTRY_ID => $countryId,
            SalesChannelContextService::COUNTRY_STATE_ID => $stateId,
        ], $saved);
    }

    public function testShippingAddressWithoutStateClearsTheContextState(): void
    {
        $countryId = Uuid::randomHex();

        $saved = $this->switchAnonymousContext(new RequestDataBag(['shippingAddress' => ['countryId' => $countryId]]));

        static::assertArrayHasKey(SalesChannelContextService::COUNTRY_STATE_ID, $saved);
        static::assertNull($saved[SalesChannelContextService::COUNTRY_STATE_ID]);
    }

    public function testBillingAddressIsStoredWithoutChangingTheContextCountry(): void
    {
        $countryId = Uuid::randomHex();

        $saved = $this->switchAnonymousContext(new RequestDataBag(['billingAddress' => ['countryId' => $countryId, 'city' => 'Wien']]));

        static::assertSame([
            SalesChannelContextService::PROSPECTIVE_CUSTOMER => [
                'billingAddress' => ['countryId' => $countryId, 'countryStateId' => null, 'zipcode' => null, 'city' => 'Wien', 'street' => null],
            ],
        ], $saved);
    }

    public function testRemovingTheShippingAddressRemovesTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag([
            'shippingAddress' => null,
            'billingAddress' => ['countryId' => Uuid::randomHex()],
        ]));

        static::assertSame([SalesChannelContextService::PROSPECTIVE_CUSTOMER => null], $saved);
    }

    public function testRemovingOnlyTheShippingAddressRemovesTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['shippingAddress' => null]), $this->createProspectiveCustomer('de'));

        static::assertSame([SalesChannelContextService::PROSPECTIVE_CUSTOMER => null], $saved);
    }

    public function testRemovingTheBillingAddressKeepsTheShippingAddress(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['billingAddress' => null]), $this->createProspectiveCustomer('de'));

        static::assertSame([SalesChannelContextService::PROSPECTIVE_CUSTOMER => ['billingAddress' => null]], $saved);
    }

    public function testRemovingTheBillingAddressDoesNotRequireAShippingAddress(): void
    {
        $definitions = [];
        $validator = static::createStub(DataValidator::class);
        $validator->method('validate')->willReturnCallback(static function (array $data, DataValidationDefinition $definition) use (&$definitions): void {
            $definitions[] = $definition;
        });

        $this->switchAnonymousContext(new RequestDataBag(['billingAddress' => null]), null, $validator);

        foreach ($definitions as $definition) {
            static::assertArrayNotHasKey('shippingAddress', $definition->getProperties());
        }
    }

    public function testChangingTheCountryRemovesTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryId' => 'at']), $this->createProspectiveCustomer('de'));

        static::assertSame([
            SalesChannelContextService::COUNTRY_ID => 'at',
            SalesChannelContextService::PROSPECTIVE_CUSTOMER => null,
        ], $saved);
    }

    public function testChangingTheCountryStateRemovesTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryStateId' => 'by']), $this->createProspectiveCustomer('de', 'nw'));

        static::assertSame([
            SalesChannelContextService::COUNTRY_STATE_ID => 'by',
            SalesChannelContextService::PROSPECTIVE_CUSTOMER => null,
        ], $saved);
    }

    public function testChangingTheCountryWhileUpdatingTheBillingAddressRemovesTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryId' => 'at', 'billingAddress' => ['countryId' => 'de']]), $this->createProspectiveCustomer('de'));

        static::assertArrayHasKey(SalesChannelContextService::PROSPECTIVE_CUSTOMER, $saved);
        static::assertNull($saved[SalesChannelContextService::PROSPECTIVE_CUSTOMER]);
    }

    public function testTheCountryOfTheStoredAddressKeepsTheProspectiveCustomer(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryId' => 'de', 'countryStateId' => '']), $this->createProspectiveCustomer('de'));

        static::assertArrayNotHasKey(SalesChannelContextService::PROSPECTIVE_CUSTOMER, $saved);
    }

    public function testCountryWithoutShippingAddressKeepsThePayloadOfOtherVisitors(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryId' => 'at']));

        static::assertSame([SalesChannelContextService::COUNTRY_ID => 'at'], $saved);
    }

    #[DataProvider('countryFieldProvider')]
    public function testCountryIsRejectedTogetherWithAShippingAddress(string $field): void
    {
        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->expects($this->never())->method('save');

        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            $persister,
            $this->createEventDispatcher(),
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectExceptionObject(SalesChannelException::prospectiveAddressWithCountry($field));

        $route->switchContext(
            new RequestDataBag([$field => Uuid::randomHex(), 'shippingAddress' => ['countryId' => Uuid::randomHex()]]),
            $this->createSalesChannelContext('token', Uuid::randomHex(), Context::createDefaultContext())
        );
    }

    public static function countryFieldProvider(): \Generator
    {
        yield 'country' => ['countryId'];
        yield 'country state' => ['countryStateId'];
    }

    public function testCountryIsAllowedWhileRemovingTheShippingAddress(): void
    {
        $saved = $this->switchAnonymousContext(new RequestDataBag(['countryId' => 'at', 'shippingAddress' => null]), $this->createProspectiveCustomer('de'));

        static::assertSame([
            SalesChannelContextService::COUNTRY_ID => 'at',
            SalesChannelContextService::PROSPECTIVE_CUSTOMER => null,
        ], $saved);
    }

    #[DataProvider('prospectiveAddressProvider')]
    public function testProspectiveAddressIsRejectedForALoggedInCustomer(string $field): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->expects($this->never())->method('save');

        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            $persister,
            $this->createEventDispatcher(),
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectExceptionObject(SalesChannelException::prospectiveAddressWithCustomer($field));

        $route->switchContext(
            new RequestDataBag([$field => ['countryId' => Uuid::randomHex()]]),
            $this->createSalesChannelContext('token', Uuid::randomHex(), Context::createDefaultContext(), $customer)
        );
    }

    public static function prospectiveAddressProvider(): \Generator
    {
        yield 'shipping address' => ['shippingAddress'];
        yield 'billing address' => ['billingAddress'];
    }

    /**
     * @return array<string, mixed> the parameters the context is saved with
     */
    private function switchAnonymousContext(RequestDataBag $data, ?ProspectiveCustomer $prospectiveCustomer = null, ?DataValidator $validator = null): array
    {
        $saved = null;
        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('save')->willReturnCallback(static function (string $token, array $parameters) use (&$saved): void {
            $saved = $parameters;
        });

        $context = $this->createSalesChannelContext('token', Uuid::randomHex(), Context::createDefaultContext(), null, $prospectiveCustomer);
        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($context);

        $route = new ContextSwitchRoute(
            $validator ?? static::createStub(DataValidator::class),
            $persister,
            $this->createEventDispatcher(),
            $contextService,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->switchContext($data, $context);
        static::assertIsArray($saved);

        return $saved;
    }

    private function createSalesChannelContext(
        string $token,
        string $salesChannelId,
        Context $frameworkContext,
        ?CustomerEntity $customer = null,
        ?ProspectiveCustomer $prospectiveCustomer = null,
    ): SalesChannelContext {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getProspectiveCustomer')
            ->willReturn($prospectiveCustomer);
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

    private function createProspectiveCustomer(string $countryId, ?string $countryStateId = null): ProspectiveCustomer
    {
        return new ProspectiveCustomer(
            new ProspectiveAddress($countryId, $countryStateId, '48624'),
            null,
            new CustomerEntity(),
        );
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
