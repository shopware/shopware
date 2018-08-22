<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class StorefrontCustomerControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    public function setUp()
    {
        parent::setUp();

        $this->serializer = $this->getContainer()->get('serializer');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        $this->countryStateRepository = $this->getContainer()->get('country_state.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testLogin()
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($email, $password);

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('x-sw-context-token', $content);
        static::assertNotEmpty($content['x-sw-context-token']);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customer, $content);
    }

    public function testLoginWithBadCredentials()
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(401, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);
    }

    public function testLogout()
    {
        $this->createCustomerAndLogin();
        $this->storefrontApiClient->request('POST', '/storefront-api/customer/logout');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
    }

    public function testGetCustomerDetail()
    {
        $customerId = $this->createCustomerAndLogin();

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customer, $content);
    }

    public function testGetAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertCount(1, $content);

        $address = $content['data'];

        static::assertEquals($customerId, $address['customerId']);
        static::assertEquals($addressId, $address['id']);
    }

    public function testGetAddresses()
    {
        $customerId = $this->createCustomerAndLogin();
        $this->createCustomerAddress($customerId);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/addresses');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertCount(2, $content['data']);
    }

    public function testCreateAddress()
    {
        $customerId = $this->createCustomerAndLogin();

        $address = [
            'salutation' => 'Mister',
            'firstName' => 'Example',
            'lastName' => 'Test',
            'street' => 'Coastal Highway 72',
            'city' => 'New York',
            'zipcode' => '12749',
            'countryId' => Defaults::COUNTRY,
            'company' => 'Shopware AG',
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/address', $address);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);

        static::assertTrue(Uuid::isValid($content['data']));

        $customerAddress = $this->readCustomerAddress($content['data']);

        static::assertEquals($customerId, $customerAddress->getCustomerId());
        static::assertEquals($address['countryId'], $customerAddress->getCountryId());
        static::assertEquals($address['salutation'], $customerAddress->getSalutation());
        static::assertEquals($address['firstName'], $customerAddress->getFirstName());
        static::assertEquals($address['lastName'], $customerAddress->getLastName());
        static::assertEquals($address['street'], $customerAddress->getStreet());
        static::assertEquals($address['zipcode'], $customerAddress->getZipcode());
        static::assertEquals($address['city'], $customerAddress->getCity());
    }

    public function testDeleteAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $customerAddress = $this->readCustomerAddress($addressId);
        static::assertInstanceOf(CustomerAddressStruct::class, $customerAddress);
        static::assertEquals($addressId, $customerAddress->getId());

        $this->storefrontApiClient->request('DELETE', '/storefront-api/customer/address/' . $addressId);

        $customerAddress = $this->readCustomerAddress($customerId);
        static::assertNull($customerAddress);
    }

    public function testSetDefaultShippingAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/default-shipping-address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($addressId, $content['data']);
        static::assertEquals($addressId, $customer->getDefaultShippingAddressId());
    }

    public function testSetDefaultBillingAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/default-billing-address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($addressId, $content['data']);
        static::assertEquals($addressId, $customer->getDefaultBillingAddressId());
    }

    public function testRegister()
    {
        $personal = [
            'salutation' => 'Mr.',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => 'test',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
        ];

        $billing = [
            'billingCountry' => Defaults::COUNTRY,
            'billingStreet' => 'Examplestreet 11',
            'billingZipcode' => '48441',
            'billingCity' => 'Cologne',
            'billingPhone' => '0123456789',
            'billingVatId' => 'DE999999999',
            'billingAdditionalAddressLine1' => 'Additional address line 1',
            'billingAdditionalAddressLine2' => 'Additional address line 2',
        ];

        $shipping = [
            'differentShippingAddress' => true,
            'shippingCountry' => Defaults::COUNTRY,
            'shippingSalutation' => 'Miss',
            'shippingFirstName' => 'Test 2',
            'shippingLastName' => 'Example 2',
            'shippingStreet' => 'Examplestreet 111',
            'shippingZipcode' => '12341',
            'shippingCity' => 'Berlin',
            'shippingPhone' => '987654321',
            'shippingAdditionalAddressLine1' => 'Additional address line 01',
            'shippingAdditionalAddressLine2' => 'Additional address line 02',
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer', array_merge($personal, $billing, $shipping));

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        $uuid = $content['data'];
        static::assertTrue(Uuid::isValid($uuid));

        $customer = $this->readCustomer($uuid);

        // verify personal data
        static::assertEquals($personal['salutation'], $customer->getSalutation());
        static::assertEquals($personal['firstName'], $customer->getFirstName());
        static::assertEquals($personal['lastName'], $customer->getLastName());
        static::assertTrue(password_verify($personal['password'], $customer->getPassword()));
        static::assertEquals($personal['email'], $customer->getEmail());
        static::assertEquals($personal['title'], $customer->getTitle());
        static::assertEquals($personal['active'], $customer->getActive());
        static::assertEquals(
            $this->formatBirthday(
                $personal['birthdayDay'],
                $personal['birthdayMonth'],
                $personal['birthdayYear']
            ),
            $customer->getBirthday()
        );

        // verify billing address
        $billingAddress = $customer->getDefaultBillingAddress();

        static::assertEquals($billing['billingCountry'], $billingAddress->getCountryId());
        static::assertEquals($personal['salutation'], $billingAddress->getSalutation());
        static::assertEquals($personal['firstName'], $billingAddress->getFirstName());
        static::assertEquals($personal['lastName'], $billingAddress->getLastName());
        static::assertEquals($billing['billingStreet'], $billingAddress->getStreet());
        static::assertEquals($billing['billingZipcode'], $billingAddress->getZipcode());
        static::assertEquals($billing['billingCity'], $billingAddress->getCity());
        static::assertEquals($billing['billingPhone'], $billingAddress->getPhoneNumber());
        static::assertEquals($billing['billingVatId'], $billingAddress->getVatId());
        static::assertEquals($billing['billingAdditionalAddressLine1'], $billingAddress->getAdditionalAddressLine1());
        static::assertEquals($billing['billingAdditionalAddressLine2'], $billingAddress->getAdditionalAddressLine2());

        // verify shipping address
        $shippingAddress = $customer->getDefaultShippingAddress();

        static::assertEquals($shipping['shippingCountry'], $shippingAddress->getCountryId());
        static::assertEquals($shipping['shippingSalutation'], $shippingAddress->getSalutation());
        static::assertEquals($shipping['shippingFirstName'], $shippingAddress->getFirstName());
        static::assertEquals($shipping['shippingLastName'], $shippingAddress->getLastName());
        static::assertEquals($shipping['shippingStreet'], $shippingAddress->getStreet());
        static::assertEquals($shipping['shippingZipcode'], $shippingAddress->getZipcode());
        static::assertEquals($shipping['shippingCity'], $shippingAddress->getCity());
        static::assertEquals($shipping['shippingPhone'], $shippingAddress->getPhoneNumber());
        static::assertEquals($shipping['shippingAdditionalAddressLine1'], $shippingAddress->getAdditionalAddressLine1());
        static::assertEquals($shipping['shippingAdditionalAddressLine2'], $shippingAddress->getAdditionalAddressLine2());
    }

    public function testChangeEmail()
    {
        $customerId = $this->createCustomerAndLogin();

        $mail = 'test@exapmle.com';
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/email', ['email' => $mail]);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $actualMail = $this->readCustomer($customerId)->getEmail();

        static::assertNull($content);
        static::assertEquals($mail, $actualMail);
    }

    public function testChangePassword()
    {
        $customerId = $this->createCustomerAndLogin();
        $password = '1234';

        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/password', ['password' => $password]);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $hash = $this->readCustomer($customerId)->getPassword();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);
        static::assertTrue(password_verify($password, $hash));
    }

    public function testChangeProfile()
    {
        $customerId = $this->createCustomerAndLogin();

        $data = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'title' => 'PHD',
            'salutation' => 'Mrs.',
            'birthdayYear' => 1900,
            'birthdayMonth' => 5,
            'birthdayDay' => 3,
        ];
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/profile', $data);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);
        static::assertEquals($data['firstName'], $customer->getFirstName());
        static::assertEquals($data['lastName'], $customer->getLastName());
        static::assertEquals($data['title'], $customer->getTitle());
        static::assertEquals($data['salutation'], $customer->getSalutation());
        static::assertEquals(
            $this->formatBirthday(
                $data['birthdayDay'],
                $data['birthdayMonth'],
                $data['birthdayYear']
            ), $customer->getBirthday()
        );
    }

    public function testGetOrdersWithoutLogin(): void
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/customer/orders');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertEquals('Customer is not logged in', $content['errors'][0]['detail'] ?? '');
    }

    public function testGetOrders(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/orders');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    public function testGetOrdersWithLimit(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();
        $this->createOrder();
        $this->createOrder();

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/orders?limit=2');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(2, $content['data']);
    }

    public function testGetOrdersWithLimitAndPage(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();
        $this->createOrder();
        $this->createOrder();

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/orders?limit=2&page=2');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::uuid4()->getHex() . '@example.com';
        $customerId = $this->createCustomer($email, $password);

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);

        return $customerId;
    }

    private function createCustomer(string $email = null, string $password): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutation' => 'Mr.',
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'additionalDescription' => 'Default payment method',
                    'technicalName' => Uuid::uuid4()->getHex(),
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutation' => 'Mr.',
                'customerNumber' => '12345',
            ],
        ], $this->context);

        return $customerId;
    }

    private function createCustomerAddress(string $customerId): string
    {
        $addressId = Uuid::uuid4()->getHex();
        $data = [
            'id' => $addressId,
            'customerId' => $customerId,
            'firstName' => 'Test',
            'lastName' => 'User',
            'street' => 'Musterstraße 2',
            'city' => 'Cologne',
            'zipcode' => '89563',
            'salutation' => 'Mrs.',
            'country' => ['name' => 'Germany'],
        ];

        $this->customerAddressRepository->upsert([$data], $this->context);

        return $addressId;
    }

    private function readCustomer(string $userID): CustomerStruct
    {
        return $this->customerRepository->read(
            new ReadCriteria([$userID]),
            Context::createDefaultContext(Defaults::TENANT_ID)
        )->get($userID);
    }

    private function readCustomerAddress(string $addressId): ?CustomerAddressStruct
    {
        return $this->customerAddressRepository->read(
            new ReadCriteria([$addressId]),
            Context::createDefaultContext(Defaults::TENANT_ID)
        )->get($addressId);
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }

    private function formatBirthday($day, $month, $year): \DateTime
    {
        return new \DateTime(sprintf(
            '%s-%s-%s',
            (int) $year,
            (int) $month,
            (int) $day
        ));
    }

    private function createOrder(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        // create new cart
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout/cart');
        $response = $this->storefrontApiClient->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        // add product
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout/cart/product/' . $productId);
        static::assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());

        // finish checkout
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout/order');
        static::assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode(), $this->storefrontApiClient->getResponse()->getContent());

        $order = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);
    }
}
