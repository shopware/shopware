<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
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
use Shopware\Core\Test\Annotation\DisabledFeatures;
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

    private MockObject&AddressListingPageLoader $addressListingPageLoader;

    private MockObject&AddressDetailPageLoader $addressDetailPageLoader;

    private MockObject&AccountService $accountService;

    private MockObject&AbstractListAddressRoute $listAddressRoute;

    private MockObject&AbstractUpsertAddressRoute $abstractUpsertAddressRoute;

    private MockObject&AbstractDeleteAddressRoute $deleteAddressRoute;

    private MockObject&AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute;

    private MockObject&AbstractContextSwitchRoute $contextSwitchRoute;

    private MockObject&SalesChannelContextService $salesChannelContextService;

    protected function setUp(): void
    {
        $this->addressListingPageLoader = $this->createMock(AddressListingPageLoader::class);
        $this->addressDetailPageLoader = $this->createMock(AddressDetailPageLoader::class);
        $this->accountService = $this->createMock(AccountService::class);
        $this->listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $this->abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $this->changeCustomerProfileRoute = $this->createMock(AbstractChangeCustomerProfileRoute::class);
        $this->contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextService::class);

        $this->controller = new AddressControllerTestClass(
            $this->addressListingPageLoader,
            $this->addressDetailPageLoader,
            $this->accountService,
            $this->listAddressRoute,
            $this->abstractUpsertAddressRoute,
            $this->deleteAddressRoute,
            $this->changeCustomerProfileRoute,
            $this->contextSwitchRoute,
            $this->salesChannelContextService
        );

        $translator = $this->createMock(TranslatorInterface::class);

        $translator->method('trans')->willReturnCallback(fn (string $key): string => $key);
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $containerBuilder->set('translator', $translator);
        $this->controller->setContainer($containerBuilder);
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    #[DisabledFeatures(['ADDRESS_SELECTION_REWORK', 'v6.7.0.0'])]
    public function testAddressBook(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(0, $renderParams['messages']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayNotHasKey('formViolations', $renderParams);
        static::assertArrayNotHasKey('postedData', $renderParams);
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    #[DisabledFeatures(['ADDRESS_SELECTION_REWORK', 'v6.7.0.0'])]
    public function testAddressBookWithConstraintViolation(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(0, $renderParams['messages']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayHasKey('formViolations', $renderParams);
        static::assertArrayHasKey('postedData', $renderParams);
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    #[DisabledFeatures(['ADDRESS_SELECTION_REWORK', 'v6.7.0.0'])]
    public function testAddressBookWithException(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $response = $this->controller->addressBook($request, $dataBag, $context, $customer);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('success', $renderParams);
        static::assertFalse($renderParams['success']);
        static::assertArrayHasKey('messages', $renderParams);
        static::assertCount(2, $renderParams['messages']);
        static::assertArrayHasKey('type', $renderParams['messages']);
        static::assertArrayHasKey('text', $renderParams['messages']);
        static::assertEquals(AddressControllerTestClass::DANGER, $renderParams['messages']['type']);
        static::assertEquals('error.message-default', $renderParams['messages']['text']);
        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayNotHasKey('formViolations', $renderParams);
        static::assertArrayNotHasKey('postedData', $renderParams);
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
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $this->controller
            ->accountCreateAddress(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer);

        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayHasKey('data', $renderParams);
    }

    public function testAccountEditAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $response = $this->controller
            ->accountEditAddress(new Request(), Generator::generateSalesChannelContext(), $customer);
        $renderParams = $this->controller->renderStorefrontParameters;

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('page', $renderParams);
    }

    public function testSwitchDefaultAddressThrowsException(): void
    {
        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'dummy-type');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller
            ->checkoutSwitchDefaultAddress($dataBag, Generator::generateSalesChannelContext(), $customer);
    }

    public function testCheckoutSwitchDefaultShippingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'shipping');
        $dataBag->set('id', Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->accountService
            ->expects(static::once())
            ->method('setDefaultShippingAddress');

        $this->accountService
            ->expects(static::never())
            ->method('setDefaultBillingAddress');

        $this->contextSwitchRoute
            ->expects(static::once())
            ->method('switchContext');

        $this->salesChannelContextService
            ->expects(static::once())
            ->method('get');

        $response = $this->controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('url:frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testCheckoutSwitchDefaultBillingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'billing');
        $dataBag->set('id', Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->accountService
            ->expects(static::once())
            ->method('setDefaultBillingAddress');

        $this->accountService
            ->expects(static::never())
            ->method('setDefaultShippingAddress');

        $this->contextSwitchRoute
            ->expects(static::once())
            ->method('switchContext');

        $this->salesChannelContextService
            ->expects(static::once())
            ->method('get');

        $response = $this->controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('url:frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testAddressManagerSwitchShippingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $id);

        $this->contextSwitchRoute
            ->expects(static::once())
            ->method('switchContext')
            ->with(
                static::callback(function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(SalesChannelContextService::BILLING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(SalesChannelContextService::SHIPPING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $this->controller->addressManagerSwitch($dataBag, $context);
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->accountService
            ->expects(static::once())
            ->method('setDefaultBillingAddress');

        $this->accountService
            ->expects(static::never())
            ->method('setDefaultShippingAddress');

        $this->controller->switchDefaultAddress('billing', Uuid::randomHex(), Generator::generateSalesChannelContext(), $customer);
    }

    public function testAddressManagerSwitchBillingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $id);

        $this->contextSwitchRoute
            ->expects(static::once())
            ->method('switchContext')
            ->with(
                static::callback(function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(SalesChannelContextService::SHIPPING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(SalesChannelContextService::BILLING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $this->controller->addressManagerSwitch($dataBag, $context);
    }

    public function testSwitchDefaultAddressWithInvalidIdThrowsException(): void
    {
        $context = Generator::generateSalesChannelContext();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        static::expectException(InvalidUuidException::class);

        $this->controller->switchDefaultAddress('shipping', 'foo', $context, $customer);
    }

    public function testSwitchDefaultBillingAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->accountService
            ->expects(static::once())
            ->method('setDefaultShippingAddress');

        $this->accountService
            ->expects(static::never())
            ->method('setDefaultBillingAddress');

        $this->controller->switchDefaultAddress('shipping', Uuid::randomHex(), Generator::generateSalesChannelContext(), $customer);
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

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        /** @var RedirectResponse $response */
        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('url:frontend.account.address.page', $response->getTargetUrl());
    }

    public function testSaveAddressWithId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('forward to frontend.account.address.edit.page', $response->getContent());
    }

    public function testSaveAddressWithoutId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['foo' => 'foo']));

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('forward to frontend.account.address.create.page', $response->getContent());
    }

    public function testDeleteAddressWithNoIdThrowsException(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        static::expectException(RoutingException::class);

        $this->controller->deleteAddress('', new Request(), Generator::generateSalesChannelContext(), $customer);
    }

    public function testDeleteAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->deleteAddressRoute
            ->expects(static::once())
            ->method('delete');

        /** @var RedirectResponse $response */
        $response = $this->controller->deleteAddress(Uuid::randomHex(), new Request(), Generator::generateSalesChannelContext(), $customer);

        static::assertSame(
            ['success' => ['account.addressDeleted']],
            $this->controller->flashBag
        );

        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('url:frontend.account.address.page', $response->getTargetUrl());
    }

    public function testDeleteAddressWithInvalidIdThrowsException(): void
    {
        $addressId = Uuid::randomHex();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->deleteAddressRoute
            ->expects(static::once())
            ->method('delete')
            ->willThrowException(new CannotDeleteDefaultAddressException($addressId));

        /** @var RedirectResponse $response */
        $response = $this->controller->deleteAddress($addressId, new Request(), Generator::generateSalesChannelContext(), $customer);

        static::assertSame(
            ['danger' => ['account.addressNotDeleted']],
            $this->controller->flashBag
        );

        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('url:frontend.account.address.page', $response->getTargetUrl());
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

        static::expectException(RoutingException::class);

        $this->controller->addressManagerUpsert(new Request(), new RequestDataBag(), Generator::generateSalesChannelContext(), $customer, Uuid::randomHex());
    }

    public function testAddressManagerWithShipping(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag();
        $dataBag->set('id', $addressId);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);
        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $this->listAddressRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects(static::once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        /** @var RedirectResponse $response */
        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $this->controller->flashBag
        );

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerWithBilling(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag();
        $dataBag->set('id', $addressId);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $this->listAddressRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects(static::once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        /** @var RedirectResponse $response */
        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'billing');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $this->controller->flashBag
        );

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerHandeltFormViolations(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag();
        $dataBag->set('id', $addressId);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $this->listAddressRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects(static::once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('formViolations', $this->controller->renderStorefrontParameters);
    }

    public function testAddressManagerHandeltErrors(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag();
        $dataBag->set('id', $addressId);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setUniqueIdentifier($addressId);
        $customerAddressCollection = new CustomerAddressCollection([$customerAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $this->listAddressRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects(static::once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $this->abstractUpsertAddressRoute
            ->expects(static::once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        static::assertArrayHasKey('messages', $this->controller->renderStorefrontParameters);

        static::assertSame(
            ['type' => 'danger', 'text' => 'error.message-default'],
            $this->controller->renderStorefrontParameters['messages']
        );
    }
}

/**
 * @internal
 */
class AddressControllerTestClass extends AddressController
{
    use StorefrontControllerMockTrait;
}
