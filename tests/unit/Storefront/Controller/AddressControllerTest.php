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

    private MockObject&AccountService $accountService;

    private MockObject&AbstractListAddressRoute $listAddressRoute;

    private MockObject&AbstractUpsertAddressRoute $abstractUpsertAddressRoute;

    private MockObject&AbstractDeleteAddressRoute $deleteAddressRoute;

    private MockObject&AbstractContextSwitchRoute $contextSwitchRoute;

    private MockObject&SalesChannelContextService $salesChannelContextService;

    protected function setUp(): void
    {
        $this->accountService = $this->createMock(AccountService::class);
        $this->listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $this->abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $this->contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextService::class);

        $this->controller = new AddressControllerTestClass(
            $this->createMock(AddressListingPageLoader::class),
            $this->createMock(AddressDetailPageLoader::class),
            $this->accountService,
            $this->listAddressRoute,
            $this->abstractUpsertAddressRoute,
            $this->deleteAddressRoute,
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
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $response = $this->controller->accountEditAddress(new Request(), Generator::generateSalesChannelContext(), $customer);
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

        $this->controller->checkoutSwitchDefaultAddress($dataBag, Generator::generateSalesChannelContext(), $customer);
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
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $this->accountService
            ->expects($this->never())
            ->method('setDefaultBillingAddress');

        $this->contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $this->salesChannelContextService
            ->expects($this->once())
            ->method('get');

        $response = $this->controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.account.addressmanager.get', $response->getTargetUrl());
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
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $this->accountService
            ->expects($this->never())
            ->method('setDefaultShippingAddress');

        $this->contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $this->salesChannelContextService
            ->expects($this->once())
            ->method('get');

        $response = $this->controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testAddressManagerSwitchShippingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $id);

        $this->contextSwitchRoute
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

        $this->controller->addressManagerSwitch($dataBag, $context);
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->accountService
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $this->accountService
            ->expects($this->never())
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

        $this->controller->addressManagerSwitch($dataBag, $context);
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

        $this->accountService
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $this->accountService
            ->expects($this->never())
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

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.account.address.page', $response->getTargetUrl());
    }

    public function testSaveAddressWithId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $this->abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.edit.page', $response->getContent());
    }

    public function testSaveAddressWithoutId(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['foo' => 'foo']));

        $this->abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->saveAddress($dataBag, Generator::generateSalesChannelContext(), $customer);

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

        $this->deleteAddressRoute
            ->expects($this->once())
            ->method('delete');

        $response = $this->controller->deleteAddress(Uuid::randomHex(), new Request(), Generator::generateSalesChannelContext(), $customer);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['success' => ['account.addressDeleted']],
            $this->controller->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.account.address.page', $response->getTargetUrl());
    }

    public function testDeleteAddressWithInvalidIdThrowsException(): void
    {
        $addressId = Uuid::randomHex();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $this->deleteAddressRoute
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new CannotDeleteDefaultAddressException($addressId));

        $response = $this->controller->deleteAddress($addressId, new Request(), Generator::generateSalesChannelContext(), $customer);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['danger' => ['account.addressNotDeleted']],
            $this->controller->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('url:frontend.account.address.page', $response->getTargetUrl());
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

        $this->listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $this->controller->flashBag
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

        $this->listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'billing');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $this->controller->flashBag
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

        $this->listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $this->abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('formViolations', $this->controller->renderStorefrontParameters);
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

        $this->listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($customerAddressCollection);

        $this->abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $response = $this->controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateSalesChannelContext(), $customer, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

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
