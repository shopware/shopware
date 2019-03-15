<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\Type\Storefront\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class StorefrontCustomerControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get('serializer');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        $this->countryStateRepository = $this->getContainer()->get('country_state.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testLogin(): void
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('x-sw-context-token', $content);
        static::assertNotEmpty($content['x-sw-context-token']);

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customer, $content);
    }

    public function testLoginWithBadCredentials(): void
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(401, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);
    }

    public function testLogout(): void
    {
        $this->createCustomerAndLogin();
        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/logout');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
    }

    public function testGetCustomerDetail(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customer, $content);
    }

    public function testGetAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/address/' . $addressId);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertCount(1, $content);

        $address = $content['data'];

        static::assertEquals($customerId, $address['customerId']);
        static::assertEquals($addressId, $address['id']);
    }

    public function testGetAddresses(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $this->createCustomerAddress($customerId);

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/address');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        static::assertCount(2, $content['data']);
    }

    public function testCreateAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $address = [
            'salutationId' => Defaults::SALUTATION_ID_MR,
            'salutation' => 'Mr.',
            'firstName' => 'Example',
            'lastName' => 'Test',
            'street' => 'Coastal Highway 72',
            'city' => 'New York',
            'zipcode' => '12749',
            'countryId' => Defaults::COUNTRY,
            'company' => 'Shopware AG',
        ];

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/address', $address);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);

        static::assertTrue(Uuid::isValid($content['data']));

        $customerAddress = $this->readCustomerAddress($content['data']);

        static::assertEquals($customerId, $customerAddress->getCustomerId());
        static::assertEquals($address['countryId'], $customerAddress->getCountryId());
        static::assertEquals($address['salutation'], $customerAddress->getSalutation()->getName());
        static::assertEquals($address['firstName'], $customerAddress->getFirstName());
        static::assertEquals($address['lastName'], $customerAddress->getLastName());
        static::assertEquals($address['street'], $customerAddress->getStreet());
        static::assertEquals($address['zipcode'], $customerAddress->getZipcode());
        static::assertEquals($address['city'], $customerAddress->getCity());
    }

    public function testDeleteAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $customerAddress = $this->readCustomerAddress($addressId);
        static::assertInstanceOf(CustomerAddressEntity::class, $customerAddress);
        static::assertEquals($addressId, $customerAddress->getId());

        $this->getStorefrontClient()->request('DELETE', '/storefront-api/v1/customer/address/' . $addressId);

        $customerAddress = $this->readCustomerAddress($customerId);
        static::assertNull($customerAddress);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/customer/address/' . $addressId . '/default-shipping');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($addressId, $content['data']);
        static::assertEquals($addressId, $customer->getDefaultShippingAddressId());
    }

    public function testSetDefaultBillingAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/customer/address/' . $addressId . '/default-billing');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($addressId, $content['data']);
        static::assertEquals($addressId, $customer->getDefaultBillingAddressId());
    }

    public function testRegister(): void
    {
        $personal = [
            'salutationId' => Defaults::SALUTATION_ID_MR,
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
            'billingAddress.country' => Defaults::COUNTRY,
            'billingAddress.street' => 'Examplestreet 11',
            'billingAddress.zipcode' => '48441',
            'billingAddress.city' => 'Cologne',
            'billingAddress.phone' => '0123456789',
            'billingAddress.vatId' => 'DE999999999',
            'billingAddress.additionalAddressLine1' => 'Additional address line 1',
            'billingAddress.additionalAddressLine2' => 'Additional address line 2',
        ];

        $shipping = [
            'shippingAddress.country' => Defaults::COUNTRY,
            'shippingAddress.salutationId' => Defaults::SALUTATION_ID_MISS,
            'shippingAddress.salutation' => 'Miss',
            'shippingAddress.firstName' => 'Test 2',
            'shippingAddress.lastName' => 'Example 2',
            'shippingAddress.street' => 'Examplestreet 111',
            'shippingAddress.zipcode' => '12341',
            'shippingAddress.city' => 'Berlin',
            'shippingAddress.phone' => '987654321',
            'shippingAddress.additionalAddressLine1' => 'Additional address line 01',
            'shippingAddress.additionalAddressLine2' => 'Additional address line 02',
        ];

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer', array_merge($personal, $billing, $shipping));

        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        $uuid = $content['data'];
        static::assertTrue(Uuid::isValid($uuid));

        $customer = $this->readCustomer($uuid);

        // verify personal data
        static::assertEquals($personal['salutation'], $customer->getSalutation()->getName());
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

        static::assertEquals($billing['billingAddress.country'], $billingAddress->getCountryId());
        static::assertEquals($personal['salutation'], $billingAddress->getSalutation()->getName());
        static::assertEquals($personal['firstName'], $billingAddress->getFirstName());
        static::assertEquals($personal['lastName'], $billingAddress->getLastName());
        static::assertEquals($billing['billingAddress.street'], $billingAddress->getStreet());
        static::assertEquals($billing['billingAddress.zipcode'], $billingAddress->getZipcode());
        static::assertEquals($billing['billingAddress.city'], $billingAddress->getCity());
        static::assertEquals($billing['billingAddress.phone'], $billingAddress->getPhoneNumber());
        static::assertEquals($billing['billingAddress.vatId'], $billingAddress->getVatId());
        static::assertEquals($billing['billingAddress.additionalAddressLine1'], $billingAddress->getAdditionalAddressLine1());
        static::assertEquals($billing['billingAddress.additionalAddressLine2'], $billingAddress->getAdditionalAddressLine2());

        // verify shipping address
        $shippingAddress = $customer->getDefaultShippingAddress();

        static::assertEquals($shipping['shippingAddress.country'], $shippingAddress->getCountryId());
        static::assertEquals($shipping['shippingAddress.salutation'], $shippingAddress->getSalutation()->getName());
        static::assertEquals($shipping['shippingAddress.firstName'], $shippingAddress->getFirstName());
        static::assertEquals($shipping['shippingAddress.lastName'], $shippingAddress->getLastName());
        static::assertEquals($shipping['shippingAddress.street'], $shippingAddress->getStreet());
        static::assertEquals($shipping['shippingAddress.zipcode'], $shippingAddress->getZipcode());
        static::assertEquals($shipping['shippingAddress.city'], $shippingAddress->getCity());
        static::assertEquals($shipping['shippingAddress.phone'], $shippingAddress->getPhoneNumber());
        static::assertEquals($shipping['shippingAddress.additionalAddressLine1'], $shippingAddress->getAdditionalAddressLine1());
        static::assertEquals($shipping['shippingAddress.additionalAddressLine2'], $shippingAddress->getAdditionalAddressLine2());
    }

    public function testChangeEmail(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $mail = 'test@exapmle.com';
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/customer/email', ['email' => $mail]);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $actualMail = $this->readCustomer($customerId)->getEmail();

        static::assertNull($content);
        static::assertEquals($mail, $actualMail);
    }

    public function testChangePassword(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $password = '1234';

        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/customer/password', ['password' => $password]);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $hash = $this->readCustomer($customerId)->getPassword();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);
        static::assertTrue(password_verify($password, $hash));
    }

    public function testChangeProfile(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $data = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'title' => 'PHD',
            'salutationId' => Defaults::SALUTATION_ID_MRS,
            'salutation' => 'Mrs.',
            'birthdayYear' => 1900,
            'birthdayMonth' => 5,
            'birthdayDay' => 3,
        ];
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/customer', $data);
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);
        static::assertEquals($data['firstName'], $customer->getFirstName());
        static::assertEquals($data['lastName'], $customer->getLastName());
        static::assertEquals($data['title'], $customer->getTitle());
        static::assertEquals($data['salutation'], $customer->getSalutation()->getName());
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
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/order');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertEquals('Customer is not logged in', $content['errors'][0]['detail'] ?? '');
    }

    public function testGetOrders(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/order');
        $response = $this->getStorefrontClient()->getResponse();
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

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/order?limit=2');
        $response = $this->getStorefrontClient()->getResponse();
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

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/customer/order?limit=2&page=2');
        $response = $this->getStorefrontClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::uuid4()->getHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);

        return $customerId;
    }

    private function createCustomer(string $password, ?string $email = null): string
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
                    'salutationId' => Defaults::SALUTATION_ID_MR,
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
                'salutationId' => Defaults::SALUTATION_ID_MR,
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
            'salutationId' => Defaults::SALUTATION_ID_MRS,
            'country' => ['name' => 'Germany'],
        ];

        $this->customerAddressRepository->upsert([$data], $this->context);

        return $addressId;
    }

    private function readCustomer(string $userID): CustomerEntity
    {
        return $this->customerRepository->search(
            new Criteria([$userID]),
            Context::createDefaultContext()
        )->get($userID);
    }

    private function readCustomerAddress(string $addressId): ?CustomerAddressEntity
    {
        return $this->customerAddressRepository->search(
            new Criteria([$addressId]),
            Context::createDefaultContext()
        )->get($addressId);
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }

    private function formatBirthday($day, $month, $year): \DateTimeInterface
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
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        // create new cart
        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/checkout/cart');
        $response = $this->getStorefrontClient()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        // add product
        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/checkout/cart/product/' . $productId);
        static::assertSame(200, $this->getStorefrontClient()->getResponse()->getStatusCode(), $this->getStorefrontClient()->getResponse()->getContent());

        // finish checkout
        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/checkout/order');
        static::assertSame(200, $this->getStorefrontClient()->getResponse()->getStatusCode(), $this->getStorefrontClient()->getResponse()->getContent());

        $order = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);
    }
}
