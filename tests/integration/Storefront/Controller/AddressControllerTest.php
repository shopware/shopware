<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\AddressController;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 *
 * @internal
 */
class AddressControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private EntityRepository $customerRepository;

    private string $addressId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->addressId = Uuid::randomHex();
    }

    public function testDeleteAddressOfOtherCustomer(): void
    {
        [$id1, $id2] = $this->createCustomers();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $id1]);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($id1, $customer->getId());

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $this->getContainer()->get('request_stack')->push($request);

        $controller->deleteAddress($id2, $context, $customer);

        $criteria = new Criteria([$id2]);

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $address = $repository->search($criteria, $context->getContext())
            ->get($id2);

        static::assertInstanceOf(CustomerAddressEntity::class, $address);

        $controller->deleteAddress($id1, $context, $customer);

        $criteria = new Criteria([$id1]);

        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $exists = $repository
            ->search($criteria, $context->getContext())
            ->has($id2);

        static::assertFalse($exists);
    }

    public function testCreateBillingAddressIsNewSelectedAddress(): void
    {
        [$customerId] = $this->createCustomers();

        $context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        $this->getContainer()->get('request_stack')->push($request);

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

    public function testCreateShippingAddressIsNewSelectedAddress(): void
    {
        [$customerId] = $this->createCustomers();

        $context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                ]
            );

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->setSession($this->getSession());

        $this->getContainer()->get('request_stack')->push($request);

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

    public function testChangeVatIds(): void
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();
        $paymentMethodId = $this->getValidPaymentMethodId();

        $customers = [
            [
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
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'First name',
                'salutationId' => $salutationId,
                'customerNumber' => 'not',
            ],
        ];
        $this->customerRepository->create($customers, Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customerId]);

        static::assertInstanceOf(CustomerEntity::class, $context->getCustomer());
        static::assertSame($customerId, $context->getCustomer()->getId());

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $this->getContainer()->get('request_stack')->push($request);

        $vatIds = ['DE123456789'];
        $requestDataBag = new RequestDataBag(['vatIds' => $vatIds]);
        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $controller->addressBook($request, $requestDataBag, $context, $customer);

        $criteria = new Criteria([$customerId]);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context->getContext())
            ->get($customerId);

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($vatIds, $customer->getVatIds());
    }

    public function testHandleViolationExceptionWhenChangeAddress(): void
    {
        $this->setPostalCodeOfTheCountryToBeRequired();

        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();
        $paymentMethodId = $this->getValidPaymentMethodId();

        $customers = [
            [
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
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'First name',
                'salutationId' => $salutationId,
                'customerNumber' => 'not',
            ],
        ];
        $this->customerRepository->create($customers, Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $this->getContainer()->get('request_stack')->push($request);

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
            $this->getContainer()->get('event_dispatcher'),
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

    public function testHandleExceptionWhenChangeAddress(): void
    {
        $customer = $this->createCustomer();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $customer->getId()]);

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $this->getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag([
            'selectAddress' => new RequestDataBag([
                'id' => 'random',
                'type' => 'random-type',
            ]),
        ]);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
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

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-listing-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressCreate(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/create');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressEdit(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/' . $this->addressId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
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

        $data = [
            [
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
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'test12345',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $context = Context::createDefaultContext();

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, $context);

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
        $paymentMethodId = $this->getValidPaymentMethodId();

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
                'defaultPaymentMethodId' => $paymentMethodId,
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
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'First name',
                'salutationId' => $salutationId,
                'customerNumber' => 'not',
            ],
        ];

        $this->customerRepository->create($customers, Context::createDefaultContext());

        return [$id1, $id2];
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
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('country.repository');

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
        $this->getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `postal_code_required` = 1
                 WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($this->getValidCountryId()),
            ]);
    }
}
