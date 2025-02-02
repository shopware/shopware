<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\AddressController;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AddressControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private const ADDRESS_TYPE_BILLING = 'billing';
    private const ADDRESS_TYPE_SHIPPING = 'shipping';

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private string $addressId;

    private CustomerEntity $loginCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRepository = static::getContainer()->get('customer.repository');

        $this->addressId = Uuid::randomHex();
    }

    public function testDeleteAddressOfOtherCustomer(): void
    {
        [$id1, $id2] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());
        static::getContainer()->get('request_stack')->push($request);

        $controller->deleteAddress($id2, $request, $context, $customer);

        $criteria = new Criteria([$id2]);

        /** @var EntityRepository<CustomerAddressCollection> $repository */
        $repository = static::getContainer()->get('customer_address.repository');
        $address = $repository->search($criteria, $context->getContext())
            ->get($id2);

        static::assertInstanceOf(CustomerAddressEntity::class, $address);

        $controller->deleteAddress($id1, $request, $context, $customer);

        $criteria = new Criteria([$id1]);

        /** @var EntityRepository<CustomerAddressCollection> $repository */
        $repository = static::getContainer()->get('customer_address.repository');
        $exists = $repository
            ->search($criteria, $context->getContext())
            ->has($id2);

        static::assertFalse($exists);
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    public function testCreateBillingAddressIsNewSelectedAddress(): void
    {
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        [$customerId] = $this->createCustomers();

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $customer1 = $context->getCustomer();
        static::assertNotNull($customer1);
        $oldBillingAddressId = $customer1->getDefaultBillingAddressId();
        $oldShippingAddressId = $customer1->getDefaultShippingAddressId();

        $dataBag = $this->getDataBag('billing');
        $controller->addressBook($request, $dataBag, $context, $customer1);
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        static::assertNotSame($oldBillingAddressId, $customer->getDefaultBillingAddressId());
        static::assertSame($oldShippingAddressId, $customer->getDefaultShippingAddressId());
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    public function testCreateShippingAddressIsNewSelectedAddress(): void
    {
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        [$customerId] = $this->createCustomers();

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        $oldBillingAddressId = $customer->getDefaultBillingAddressId();
        $oldShippingAddressId = $customer->getDefaultShippingAddressId();

        $dataBag = $this->getDataBag('shipping');
        $controller->addressBook($request, $dataBag, $context, $customer);
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        static::assertNotSame($oldShippingAddressId, $customer->getDefaultShippingAddressId());
        static::assertSame($oldBillingAddressId, $customer->getDefaultBillingAddressId());
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    public function testChangeVatIds(): void
    {
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddress' => [
                'id' => $addressId,
                'salutationId' => $salutationId,
                'firstName' => 'foo',
                'lastName' => 'bar',
                'zipcode' => '48599',
                'city' => 'gronau',
                'street' => 'Schillerstr.',
                'countryId' => $this->getValidCountryId(),
            ],
            'company' => 'nfq',
            'defaultShippingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'lastName' => 'not',
            'firstName' => 'First name',
            'salutationId' => $salutationId,
            'customerNumber' => 'not',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->customerRepository->create([$customer], Context::createDefaultContext());

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customerId]);

        static::assertInstanceOf(CustomerEntity::class, $context->getCustomer());
        static::assertSame($customerId, $context->getCustomerId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        static::getContainer()->get('request_stack')->push($request);

        $vatIds = ['DE123456789'];
        $requestDataBag = new RequestDataBag(['vatIds' => $vatIds]);
        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $controller->addressBook($request, $requestDataBag, $context, $customer);

        $criteria = new Criteria([$customerId]);

        $customer = $this->customerRepository->search($criteria, $context->getContext())->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($vatIds, $customer->getVatIds());
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    public function testHandleViolationExceptionWhenChangeAddress(): void
    {
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        $this->setPostalCodeOfTheCountryToBeRequired();

        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddress' => [
                'id' => $addressId,
                'salutationId' => $salutationId,
                'firstName' => 'foo',
                'lastName' => 'bar',
                'zipcode' => '48599',
                'city' => 'gronau',
                'street' => 'Schillerstr.',
                'countryId' => $this->getValidCountryId(),
            ],
            'company' => 'ABC',
            'defaultShippingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'lastName' => 'not',
            'firstName' => 'First name',
            'salutationId' => $salutationId,
            'customerNumber' => 'not',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->customerRepository->create([$customer], Context::createDefaultContext());

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        static::getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag([
            'changeableAddresses' => new RequestDataBag([
                'changeBilling' => '1',
                'changeShipping' => '',
            ]),
            'addressId' => '',
            'accountType' => '',
            'address' => new RequestDataBag([
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'not',
                'lastName' => 'not',
                'company' => 'not',
                'department' => 'not',
                'street' => 'not',
                'zipcode' => '',
                'city' => 'not',
                'countryId' => $this->getValidCountryId(),
            ]),
        ]);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            function (StorefrontRenderEvent $event): void {
                $data = $event->getParameters();

                static::assertArrayHasKey('formViolations', $data);
                static::assertArrayHasKey('postedData', $data);
            },
            0,
            true
        );

        $controller->addressBook($request, $requestDataBag, $context, $customer);
    }

    /**
     * @deprecated tag:v6.7.0 remove
     */
    public function testHandleExceptionWhenChangeAddress(): void
    {
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        $customer = $this->createCustomer();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customer->getId()]);

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        static::getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag([
            'selectAddress' => new RequestDataBag([
                'id' => 'random',
                'type' => 'random-type',
            ]),
        ]);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            function (StorefrontRenderEvent $event): void {
                $data = $event->getParameters();

                static::assertArrayHasKey('success', $data);
                static::assertArrayHasKey('messages', $data);

                static::assertFalse($data['success']);
                static::assertSame('danger', $data['messages']['type']);
            },
            0,
            true
        );

        $controller->addressBook($request, $requestDataBag, $context, $customer);
    }

    public function testAddressListingPageLoadedScriptsAreExecuted(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-listing-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressCreate(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/create');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressEdit(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/' . $this->addressId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testCreateShippingAddressIsActiveAddress(): void
    {
        if (!Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        [$customerId] = $this->createCustomers();

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $dataBag = new RequestDataBag([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'not',
            'lastName' => 'not',
            'company' => 'not',
            'department' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'city' => 'not',
            'countryId' => $this->getValidCountryId(),
        ]);

        $controller->addressManagerUpsert($request, $dataBag, $context, $customer, null, self::ADDRESS_TYPE_SHIPPING);

        $newContext = static::getContainer()->get(SalesChannelContextPersister::class)->load($context->getToken(), TestDefaults::SALES_CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(SalesChannelContextService::SHIPPING_ADDRESS_ID, $newContext);
    }

    public function testCreateBillingAddressIsActiveAddress(): void
    {
        if (!Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            return;
        }

        [$customerId] = $this->createCustomers();

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $dataBag = new RequestDataBag([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'not',
            'lastName' => 'not',
            'company' => 'not',
            'department' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'city' => 'not',
            'countryId' => $this->getValidCountryId(),
        ]);

        $controller->addressManagerUpsert($request, $dataBag, $context, $customer, null, self::ADDRESS_TYPE_BILLING);

        $newContext = static::getContainer()->get(SalesChannelContextPersister::class)->load($context->getToken(), TestDefaults::SALES_CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(SalesChannelContextService::BILLING_ADDRESS_ID, $newContext);
    }

    public function testCheckoutSwitchDefaultShippingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newDefaultShippingAddress = $this->createCustomerAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'shipping');
        $dataBag->set('id', $newDefaultShippingAddress);

        $controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $customer = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($customer->getDefaultShippingAddressId(), $newDefaultShippingAddress);
    }

    public function testSaveAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $dataBag = new RequestDataBag([
            'address' => [
                'customerId' => $customer->getId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ]);

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $criteria = new Criteria([$id1]);
        $criteria->addAssociation('addresses');

        $customerWithOldAddress = $repo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customerWithOldAddress);
        static::assertNotNull($customerWithOldAddress->getAddresses());
        static::assertCount(1, $customerWithOldAddress->getAddresses());

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->saveAddress($dataBag, $context, $customer);

        $criteria = new Criteria([$id1]);
        $criteria->addAssociation('addresses');

        $customerNewAddress = $repo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            static::assertSame(
                ['success' => [static::getContainer()->get('translator')->trans('account.addressSaved')]],
                // @phpstan-ignore method.notFound
                $this->getSession()->getFlashBag()->all()
            );
        } else {
            static::assertStringContainsString('addressSaved=1', $response->getTargetUrl());
        }
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertInstanceOf(CustomerEntity::class, $customerNewAddress);
        static::assertNotNull($customerNewAddress->getAddresses());
        static::assertCount(2, $customerNewAddress->getAddresses());
    }

    public function testCheckoutSwitchDefaultBillingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newDefaultBillingAddress = $this->createCustomerAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'billing');
        $dataBag->set('id', $newDefaultBillingAddress);

        $controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $customer = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($customer->getDefaultBillingAddressId(), $newDefaultBillingAddress);
    }

    public function testCheckoutSwitchDefaultAddressWithInvalidType(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'foo');

        static::expectException(RoutingException::class);

        $controller->checkoutSwitchDefaultAddress($dataBag, $context, $customer);
    }

    public function testSwitchDefaultAddressWithInvalidUuid(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        static::expectException(InvalidUuidException::class);

        $controller->switchDefaultAddress(self::ADDRESS_TYPE_SHIPPING, 'foo', $context, $customer);
    }

    public function testSwitchDefaultAddressWithInvalidType(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress('foo', $customer->getDefaultBillingAddressId(), $context, $customer);

        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            static::assertSame(
                ['danger' => [static::getContainer()->get('translator')->trans('account.addressDefaultNotChanged')]],
                // @phpstan-ignore method.notFound
                $this->getSession()->getFlashBag()->all()
            );
        } else {
            static::assertStringContainsString('changedDefaultAddress=0', $response->getTargetUrl());
        }

        static::assertTrue($response->isRedirect(), (string) $response->getContent());
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $newDefaultShippingAddress = $this->createCustomerAddress($id1);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress(self::ADDRESS_TYPE_SHIPPING, $newDefaultShippingAddress, $context, $customer);

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $customer = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($customer);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            static::assertSame(
                ['success' => [static::getContainer()->get('translator')->trans('account.addressDefaultChanged')]],
                // @phpstan-ignore method.notFound
                $this->getSession()->getFlashBag()->all()
            );
        } else {
            static::assertStringContainsString('changedDefaultAddress=1', $response->getTargetUrl());
        }

        static::assertSame($newDefaultShippingAddress, $customer->getDefaultShippingAddressId());
    }

    public function testSwitchDefaultBillingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $newDefaultBillingAddress = $this->createCustomerAddress($id1);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress(self::ADDRESS_TYPE_BILLING, $newDefaultBillingAddress, $context, $customer);

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $customer = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($customer);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        if (Feature::isActive('ADDRESS_SELECTION_REWORK')) {
            static::assertSame(
                ['success' => [static::getContainer()->get('translator')->trans('account.addressDefaultChanged')]],
                // @phpstan-ignore method.notFound
                $this->getSession()->getFlashBag()->all()
            );
        } else {
            static::assertStringContainsString('changedDefaultAddress=1', $response->getTargetUrl());
        }
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertSame($newDefaultBillingAddress, $customer->getDefaultBillingAddressId());
    }

    public function testAddressManagerSwitchActiveShippingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newActiveAddress = $this->createCustomerAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $newActiveAddress);

        $controller->addressManagerSwitch($dataBag, $context);

        $newContext = static::getContainer()->get(SalesChannelContextPersister::class)->load($context->getToken(), TestDefaults::SALES_CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(SalesChannelContextService::SHIPPING_ADDRESS_ID, $newContext);
        static::assertSame($newActiveAddress, $newContext[SalesChannelContextService::SHIPPING_ADDRESS_ID]);
    }

    public function testAddressManagerSwitchActiveBillingAddress(): void
    {
        [$id1] = $this->createCustomers();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newActiveAddress = $this->createCustomerAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $newActiveAddress);

        $controller->addressManagerSwitch($dataBag, $context);

        $newContext = static::getContainer()->get(SalesChannelContextPersister::class)->load($context->getToken(), TestDefaults::SALES_CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(SalesChannelContextService::BILLING_ADDRESS_ID, $newContext);
        static::assertSame($newActiveAddress, $newContext[SalesChannelContextService::BILLING_ADDRESS_ID]);
    }

    public function testAddressManagerExceptionWhenCreating(): void
    {
        [$customerId] = $this->createCustomers();

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $dataBag = new RequestDataBag([
            'firstName' => 'not',
            'company' => 'not',
            'department' => 'not',
            'street' => 'not',
        ]);

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            function (StorefrontRenderEvent $event): void {
                $data = $event->getParameters();

                static::assertArrayHasKey('formViolations', $data);
                static::assertArrayHasKey('postedAddress', $data);
            },
            0,
            true
        );

        $controller->addressManagerUpsert($request, $dataBag, $context, $customer, null, self::ADDRESS_TYPE_SHIPPING);
    }

    public function testAccountAddressOverview(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/create');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountEditAddressWithWrongId(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/edit/1');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAccountEditAddress(): void
    {
        $browser = $this->login();

        $browser->request('GET', \sprintf(
            '/account/address/%s',
            $this->loginCustomer->getDefaultBillingAddressId()
        ));

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAddressManagerGet(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/widgets/account/address-manager');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function login(): KernelBrowser
    {
        $this->loginCustomer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $this->loginCustomer->getEmail(),
                'password' => 'test12345',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $this->addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $this->addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => 'test12345',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $context = Context::createDefaultContext();

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], $context);

        $customer = $repo->search(new Criteria([$customerId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($customer);

        return $customer;
    }

    /**
     * @return array<int, string>
     */
    private function createCustomers(): array
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $customers = [
            [
                'id' => $id1,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $id1,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'city' => 'not',
                    'street' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $salutationId,
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $id1,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'First name',
                'salutationId' => $salutationId,
                'customerNumber' => 'not',
            ],
            [
                'id' => $id2,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $id2,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'city' => 'not',
                    'street' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $salutationId,
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $id2,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'First name',
                'salutationId' => $salutationId,
                'customerNumber' => 'not',
            ],
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $paymentMethodId = $this->getValidPaymentMethodId();
            foreach ($customers as &$customer) {
                $customer['defaultPaymentMethodId'] = $paymentMethodId;
            }
        }

        $this->customerRepository->create($customers, Context::createDefaultContext());

        return [$id1, $id2];
    }

    private function createCustomerAddress(string $customerId): string
    {
        $newBillingAddressId = Uuid::randomHex();
        $repository = static::getContainer()->get('customer_address.repository');
        $repository->create([
            [
                'id' => $newBillingAddressId,
                'customerId' => $customerId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ], Context::createDefaultContext());

        return $newBillingAddressId;
    }

    private function getDataBag(string $type): RequestDataBag
    {
        return new RequestDataBag([
            'changeableAddresses' => new RequestDataBag([
                'changeBilling' => ($type === 'billing') ? '1' : '',
                'changeShipping' => ($type === 'shipping') ? '1' : '',
            ]),
            'addressId' => '',
            'accountType' => '',
            'address' => new RequestDataBag([
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'not',
                'lastName' => 'not',
                'company' => 'not',
                'department' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'city' => 'not',
                'countryId' => $this->getValidCountryId(),
            ]),
        ]);
    }

    private function getValidCountryId(?string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        if ($salesChannelId !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        }

        return (string) $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    private function setPostalCodeOfTheCountryToBeRequired(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `postal_code_required` = 1
                 WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($this->getValidCountryId()),
            ]);
    }
}
