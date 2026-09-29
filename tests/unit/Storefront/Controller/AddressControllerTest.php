<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\AddressController;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressController::class)]
class AddressControllerTest extends TestCase
{
    private AddressControllerTestClass $controller;

    private Stub&AccountService $accountService;

    private Stub&AbstractListAddressRoute $listAddressRoute;

    private Stub&AbstractUpsertAddressRoute $abstractUpsertAddressRoute;

    private Stub&AbstractDeleteAddressRoute $deleteAddressRoute;

    private Stub&AbstractContextSwitchRoute $contextSwitchRoute;

    private Stub&SalesChannelContextService $salesChannelContextService;

    protected function setUp(): void
    {
        $this->accountService = static::createStub(AccountService::class);
        $this->listAddressRoute = static::createStub(AbstractListAddressRoute::class);
        $this->abstractUpsertAddressRoute = static::createStub(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = static::createStub(AbstractDeleteAddressRoute::class);
        $this->contextSwitchRoute = static::createStub(AbstractContextSwitchRoute::class);
        $this->salesChannelContextService = static::createStub(SalesChannelContextService::class);

        $this->controller = $this->buildController();
    }

    public function testAccountAddressOverview(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $response = $this->controller
            ->accountAddressOverview(new Request(), Generator::generateSalesChannelContext(), $customer);

        static::assertSame(
            '@Storefront/storefront/page/account/addressbook/index.html.twig',
            $this->controller->renderStorefrontView
        );
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $this->controller->accountCreateAddress(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer);

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayHasKey('data', $renderParams);
    }

    public function testAccountEditAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $request = new Request();
        $request->query->set('redirectTo', 'foo');

        $response = $this->controller->accountEditAddress($request, Generator::generateSalesChannelContext(), $customer);
        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('page', $renderParams);
        static::assertSame('foo', $renderParams['redirectTo'] ?? null);
    }

    public function testSwitchDefaultAddressThrowsException(): void
    {
        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'dummy-type');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer);
    }

    public function testCheckoutSwitchDefaultShippingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'shipping');
        $dataBag->set('id', Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultBillingAddress');

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $salesChannelContextService = $this->createMock(SalesChannelContextService::class);
        $salesChannelContextService
            ->expects($this->once())
            ->method('get');

        $controller = $this->buildController(
            accountService: $accountService,
            contextSwitchRoute: $contextSwitchRoute,
            salesChannelContextService: $salesChannelContextService,
        );

        $response = $controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, $context, $customer);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testCheckoutSwitchDefaultBillingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'billing');
        $dataBag->set('id', Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultShippingAddress');

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $salesChannelContextService = $this->createMock(SalesChannelContextService::class);
        $salesChannelContextService
            ->expects($this->once())
            ->method('get');

        $controller = $this->buildController(
            accountService: $accountService,
            contextSwitchRoute: $contextSwitchRoute,
            salesChannelContextService: $salesChannelContextService,
        );

        $response = $controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, $context, $customer);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testAddressManagerSwitchShippingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $request = new Request();
        $request->request->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $id);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(SalesChannelContextService::BILLING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(SalesChannelContextService::SHIPPING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $controller = $this->buildController(contextSwitchRoute: $contextSwitchRoute);

        $controller->addressManagerSwitch($request, $context);
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultShippingAddress');

        $controller = $this->buildController(accountService: $accountService);

        $controller->switchDefaultAddress('billing', Uuid::randomHex(), Generator::generateSalesChannelContext(), $customer);
    }

    public function testAddressManagerSwitchBillingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $request = new Request();
        $request->request->set(SalesChannelContextService::BILLING_ADDRESS_ID, $id);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(SalesChannelContextService::SHIPPING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(SalesChannelContextService::BILLING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $controller = $this->buildController(contextSwitchRoute: $contextSwitchRoute);

        $controller->addressManagerSwitch($request, $context);
    }

    public function testSwitchDefaultAddressWithInvalidIdThrowsException(): void
    {
        $context = Generator::generateSalesChannelContext();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->expectException(InvalidUuidException::class);

        $this->controller->switchDefaultAddress('shipping', 'foo', $context, $customer);
    }

    public function testSwitchDefaultBillingAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultBillingAddress');

        $controller = $this->buildController(accountService: $accountService);

        $controller->switchDefaultAddress('shipping', Uuid::randomHex(), Generator::generateSalesChannelContext(), $customer);
    }

    public function testSwitchDefaultBillingAddressWithInvalidId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $addressId = Uuid::randomHex();

        $this->accountService
            ->method('setDefaultShippingAddress')
            ->willThrowException(new AddressNotFoundException($addressId));

        $this->controller->switchDefaultAddress('shipping', $addressId, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(
            ['danger' => ['account.addressDefaultNotChanged']],
            $this->controller->flashBag
        );
    }

    public function testSwitchDefaultBillingAddressWithInvalidTye(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $addressId = Uuid::randomHex();

        $this->accountService
            ->method('setDefaultShippingAddress')
            ->willThrowException(new AddressNotFoundException($addressId));

        $this->controller->switchDefaultAddress('foo', $addressId, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(
            ['danger' => ['account.addressDefaultNotChanged']],
            $this->controller->flashBag
        );
    }

    public function testSaveAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer, new Request());
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testSaveAddressWithId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(abstractUpsertAddressRoute: $abstractUpsertAddressRoute);

        $response = $controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.edit.page', $response->getContent());
    }

    public function testSaveAddressWithoutId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['foo' => 'foo']));

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(abstractUpsertAddressRoute: $abstractUpsertAddressRoute);

        $response = $controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.create.page', $response->getContent());
    }

    public function testDeleteAddressWithNoIdThrowsException(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->deleteAddress('', new Request(), Generator::generateSalesChannelContext(), $customer);
    }

    public function testDeleteAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute
            ->expects($this->once())
            ->method('delete');

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);

        $response = $controller->deleteAddress(Uuid::randomHex(), new Request(), Generator::generateSalesChannelContext(), $customer);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['success' => ['account.addressDeleted']],
            $controller->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testDeleteAddressWithInvalidIdThrowsException(): void
    {
        $addressId = Uuid::randomHex();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new CannotDeleteDefaultAddressException($addressId));

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);

        $response = $controller->deleteAddress($addressId, new Request(), Generator::generateSalesChannelContext(), $customer);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['danger' => ['account.addressNotDeleted']],
            $controller->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testAddressManager(): void
    {
        $request = new Request();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $response = $this->controller->addressManager($request, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            '@Storefront/storefront/component/address/address-manager-modal.html.twig',
            $this->controller->renderStorefrontView
        );
    }

    public function testAddressManagerWithoutType(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->addressManagerUpsert(new Request(), new RequestDataBag(), Generator::generateSalesChannelContext(), $customer, Uuid::randomHex());
    }

    public function testAddressManagerWithShipping(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);
        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $controller = $this->buildController(listAddressRoute: $listAddressRoute);

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $controller->flashBag
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerWithBilling(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $controller = $this->buildController(listAddressRoute: $listAddressRoute);

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'billing');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $controller->flashBag
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerHandeltFormViolations(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(
            listAddressRoute: $listAddressRoute,
            abstractUpsertAddressRoute: $abstractUpsertAddressRoute,
        );

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('formViolations', $controller->renderStorefrontParameters);
    }

    public function testAddressManagerHandeltErrors(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $controller = $this->buildController(
            listAddressRoute: $listAddressRoute,
            abstractUpsertAddressRoute: $abstractUpsertAddressRoute,
        );

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertArrayHasKey('messages', $controller->renderStorefrontParameters);

        static::assertSame(
            ['type' => 'danger', 'text' => 'error.message-default'],
            $controller->renderStorefrontParameters['messages']
        );
    }

    private function buildController(
        ?AccountService $accountService = null,
        ?AbstractListAddressRoute $listAddressRoute = null,
        ?AbstractUpsertAddressRoute $abstractUpsertAddressRoute = null,
        ?AbstractDeleteAddressRoute $deleteAddressRoute = null,
        ?AbstractContextSwitchRoute $contextSwitchRoute = null,
        ?SalesChannelContextService $salesChannelContextService = null,
    ): AddressControllerTestClass {
        $controller = new AddressControllerTestClass(
            static::createStub(AddressListingPageLoader::class),
            static::createStub(AddressDetailPageLoader::class),
            $accountService ?? $this->accountService,
            $listAddressRoute ?? $this->listAddressRoute,
            $abstractUpsertAddressRoute ?? $this->abstractUpsertAddressRoute,
            $deleteAddressRoute ?? $this->deleteAddressRoute,
            $contextSwitchRoute ?? $this->contextSwitchRoute,
            $salesChannelContextService ?? $this->salesChannelContextService
        );

        $translator = static::createStub(TranslatorInterface::class);

        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $containerBuilder->set('translator', $translator);
        $controller->setContainer($containerBuilder);

        return $controller;
    }
}

/**
 * @internal
 */
class AddressControllerTestClass extends AddressController
{
    use StorefrontControllerMockTrait;
}
