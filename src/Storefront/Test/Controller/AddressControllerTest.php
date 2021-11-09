<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
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
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AddressControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private EntityRepositoryInterface $customerRepository;

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

        static::assertInstanceOf(CustomerEntity::class, $context->getCustomer());
        static::assertSame($id1, $context->getCustomer()->getId());

        $controller = $this->getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $this->getContainer()->get('request_stack')->push($request);

        $controller->deleteAddress($id2, $context, $context->getCustomer());

        $criteria = new Criteria([$id2]);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $address = $repository->search($criteria, $context->getContext())
            ->get($id2);

        static::assertInstanceOf(CustomerAddressEntity::class, $address);

        $controller->deleteAddress($id1, $context, $context->getCustomer());

        $criteria = new Criteria([$id1]);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $exists = $repository
            ->search($criteria, $context->getContext())
            ->has($id2);

        static::assertFalse($exists);
    }

    public function testCreateBillingAddressIsNewSelectedAddress(): void
    {
        [$customerId, ] = $this->createCustomers();

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
        $request->setSession($this->getContainer()->get('session'));

        $this->getContainer()->get('request_stack')->push($request);

        $oldBillingAddressId = $context->getCustomer()->getDefaultBillingAddressId();
        $oldShippingAddressId = $context->getCustomer()->getDefaultShippingAddressId();

        $dataBag = $this->getDataBag('billing');
        $controller->addressBook($request, $dataBag, $context, $context->getCustomer());
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->first();

        static::assertNotSame($oldBillingAddressId, $customer->getDefaultBillingAddressId());
        static::assertSame($oldShippingAddressId, $customer->getDefaultShippingAddressId());
    }

    public function testCreateShippingAddressIsNewSelectedAddress(): void
    {
        [$customerId, ] = $this->createCustomers();

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
        $request->setSession($this->getContainer()->get('session'));

        $this->getContainer()->get('request_stack')->push($request);

        $oldBillingAddressId = $context->getCustomer()->getDefaultBillingAddressId();
        $oldShippingAddressId = $context->getCustomer()->getDefaultShippingAddressId();

        $dataBag = $this->getDataBag('shipping');
        $controller->addressBook($request, $dataBag, $context, $context->getCustomer());
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->first();

        static::assertNotSame($oldShippingAddressId, $customer->getDefaultShippingAddressId());
        static::assertSame($oldBillingAddressId, $customer->getDefaultBillingAddressId());
    }

    public function testChangeVatIds(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_15957', $this);

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
                    'country' => ['name' => 'not'],
                ],
                'company' => 'nfq',
                'defaultShippingAddressId' => $addressId,
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
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
        $controller->addressBook($request, $requestDataBag, $context, $context->getCustomer());

        $criteria = new Criteria([$customerId]);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context->getContext())
            ->get($customerId);

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame($vatIds, $customer->getVatIds());
    }

    public function testAddressListingPageLoadedScriptsAreExecuted(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17441', $this);

        $browser = $this->login();

        $browser->request('GET', '/account/address');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-listing-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressCreate(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17441', $this);

        $browser = $this->login();

        $browser->request('GET', '/account/address/create');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressEdit(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17441', $this);

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
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

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
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

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
                'password' => 'not',
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
                'password' => 'not',
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
}
