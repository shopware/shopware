<?php declare(strict_types=1);

namespace Shopware\Checkout\Test\Customer;

use Ramsey\Uuid\Uuid;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Checkout\Customer\CustomerRepository;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Content\Product\ProductRepository;
use Shopware\Defaults;
use Shopware\Framework\Api\Response\Type\JsonType;
use Shopware\Framework\Test\Api\ApiTestCase;
use Shopware\System\Country\Aggregate\CountryState\CountryStateRepository;
use Shopware\System\Country\CountryRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Serializer;

class StorefrontCustomerControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var CountryRepository
     */
    private $countryRepository;

    /**
     * @var CountryStateRepository
     */
    private $countryStateRepository;

    /**
     * @var ApplicationContext
     */
    private $applicationContext;

    /**
     * @var CustomerAddressRepository
     */
    private $customerAddressRepository;

    public function setUp()
    {
        parent::setUp();

        $this->serializer = $this->getContainer()->get('serializer');
        $this->productRepository = $this->getContainer()->get(ProductRepository::class);
        $this->customerRepository = $this->getContainer()->get(CustomerRepository::class);
        $this->countryRepository = $this->getContainer()->get(CountryRepository::class);
        $this->customerAddressRepository = $this->getContainer()->get(CustomerAddressRepository::class);
        $this->countryStateRepository = $this->getContainer()->get(CountryStateRepository::class);
        $this->applicationContext = ApplicationContext::createDefaultContext(\Shopware\Defaults::TENANT_ID);
    }

    public function testLogin()
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($email, $password);

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [], [], [], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('x-sw-context-token', $content);
        $this->assertNotEmpty($content['x-sw-context-token']);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertEquals($customer, $content);
    }

    public function testLoginWithBadCredentials()
    {
        $email = Uuid::uuid4()->getHex() . '@example.com';
        $password = 'shopware';

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [], [], [], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);

        $this->expectException(HttpException::class);
        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);
    }

    public function testLogout()
    {
        $this->createCustomerAndLogin();
        $this->storefrontApiClient->request('POST', '/storefront-api/customer/logout');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertNull($content);

        $this->expectException(HttpException::class);
        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
    }

    public function testGetCustomerDetail()
    {
        $customerId = $this->createCustomerAndLogin();

        $this->storefrontApiClient->request('GET', '/storefront-api/customer');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertEquals($customer, $content);
    }

    public function testGetAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $this->assertCount(1, $content);

        $address = $content['data'];

        $this->assertEquals($customerId, $address['customerId']);
        $this->assertEquals($addressId, $address['id']);
    }

    public function testGetAddresses()
    {
        $customerId = $this->createCustomerAndLogin();
        $this->createCustomerAddress($customerId);

        $this->storefrontApiClient->request('GET', '/storefront-api/customer/addresses');
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $this->assertCount(2, $content['data']);
    }

    public function testCreateAddress()
    {
        $customerId = $this->createCustomerAndLogin();

        $address = [
            'salutation' => 'Mister',
            'firstname' => 'Example',
            'lastname' => 'Test',
            'street' => 'Coastal Highway 72',
            'city' => 'New York',
            'zipcode' => '12749',
            'country' => Defaults::COUNTRY,
            'company' => 'Shopware AG',
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/address', [], [], [], json_encode($address));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);

        $this->assertTrue(\Shopware\Framework\Struct\Uuid::isValid($content['data']));

        $customerAddress = $this->readCustomerAddress($content['data']);

        $this->assertEquals($customerId, $customerAddress->getCustomerId());
        $this->assertEquals($address['country'], $customerAddress->getCountryId());
        $this->assertEquals($address['salutation'], $customerAddress->getSalutation());
        $this->assertEquals($address['firstname'], $customerAddress->getFirstName());
        $this->assertEquals($address['lastname'], $customerAddress->getLastName());
        $this->assertEquals($address['street'], $customerAddress->getStreet());
        $this->assertEquals($address['zipcode'], $customerAddress->getZipcode());
        $this->assertEquals($address['city'], $customerAddress->getCity());
    }

    public function testDeleteAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $customerAddress = $this->readCustomerAddress($addressId);
        $this->assertInstanceOf(CustomerAddressBasicStruct::class, $customerAddress);
        $this->assertEquals($addressId, $customerAddress->getId());

        $this->storefrontApiClient->request('DELETE', '/storefront-api/customer/address/' . $addressId);

        $customerAddress = $this->readCustomerAddress($customerId);
        $this->assertNull($customerAddress);
    }

    public function testSetDefaultShippingAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/default-shipping-address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertNotEmpty($content['data']);
        $this->assertEquals($addressId, $content['data']);
        $this->assertEquals($addressId, $customer->getDefaultShippingAddressId());
    }

    public function testSetDefaultBillingAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/default-billing-address/' . $addressId);
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertNotEmpty($content['data']);
        $this->assertEquals($addressId, $content['data']);
        $this->assertEquals($addressId, $customer->getDefaultBillingAddressId());
    }

    public function testRegister()
    {
        $countryStateId = '9f834bad88204d9896f31993624ac74c';

        $personal = [
            'salutation' => 'Mr.',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'password' => 'test',
            'email' => 'test@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthday' => [
                'year' => 2000,
                'month' => 1,
                'day' => 22,
            ],
        ];

        $billing = [
            'country' => Defaults::COUNTRY,
            'salutation' => 'Mrs.',
            'firstname' => 'Test',
            'lastname' => 'Example',
            'street' => 'Examplestreet 11',
            'zipcode' => '48441',
            'city' => 'Cologne',
            'phone' => '0123456789',
            'vatId' => 'DE999999999',
            'additionalAddressLine1' => 'Additional address line 1',
            'additionalAddressLine2' => 'Additional address line 2',
            'country_state' => $countryStateId,
        ];

        $shipping = [
            'country' => Defaults::COUNTRY,
            'salutation' => 'Miss',
            'firstname' => 'Test 2',
            'lastname' => 'Example 2',
            'street' => 'Examplestreet 111',
            'zipcode' => '12341',
            'city' => 'Berlin',
            'phone' => '987654321',
            'vatId' => 'DE88888888',
            'additionalAddressLine1' => 'Additional address line 01',
            'additionalAddressLine2' => 'Additional address line 02',
            'country_state' => $countryStateId,
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer', [], [], [],
            json_encode([
                'personal' => $personal,
                'billing' => $billing,
                'shipping' => $shipping,
            ]));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $uuid = $content['data'];
        $this->assertTrue(Uuid::isValid($uuid));

        $customer = $this->readCustomer(\Shopware\Framework\Struct\Uuid::optimize($uuid));

        // verify personal data
        $this->assertEquals($personal['salutation'], $customer->getSalutation());
        $this->assertEquals($personal['firstname'], $customer->getFirstName());
        $this->assertEquals($personal['lastname'], $customer->getLastName());
        $this->assertTrue(password_verify($personal['password'], $customer->getPassword()));
        $this->assertEquals($personal['email'], $customer->getEmail());
        $this->assertEquals($personal['title'], $customer->getTitle());
        $this->assertEquals($personal['active'], $customer->getActive());
        $this->assertEquals(new \DateTime($this->formatBirthday($personal['birthday'])), $customer->getBirthday());

        // verify billing address
        $billingAddress = $customer->getDefaultBillingAddress();

        $this->assertEquals($billing['country'], $billingAddress->getCountryId());
        $this->assertEquals($billing['salutation'], $billingAddress->getSalutation());
        $this->assertEquals($billing['firstname'], $billingAddress->getFirstName());
        $this->assertEquals($billing['lastname'], $billingAddress->getLastName());
        $this->assertEquals($billing['street'], $billingAddress->getStreet());
        $this->assertEquals($billing['zipcode'], $billingAddress->getZipcode());
        $this->assertEquals($billing['city'], $billingAddress->getCity());
        $this->assertEquals($billing['phone'], $billingAddress->getPhoneNumber());
        $this->assertEquals($billing['vatId'], $billingAddress->getVatId());
        $this->assertEquals($billing['additionalAddressLine1'], $billingAddress->getAdditionalAddressLine1());
        $this->assertEquals($billing['additionalAddressLine2'], $billingAddress->getAdditionalAddressLine2());
        $this->assertEquals($billing['country_state'], $billingAddress->getCountryStateId());

        // verify shipping address
        $shippingAddress = $customer->getDefaultShippingAddress();

        $this->assertEquals($shipping['country'], $shippingAddress->getCountryId());
        $this->assertEquals($shipping['salutation'], $shippingAddress->getSalutation());
        $this->assertEquals($shipping['firstname'], $shippingAddress->getFirstName());
        $this->assertEquals($shipping['lastname'], $shippingAddress->getLastName());
        $this->assertEquals($shipping['street'], $shippingAddress->getStreet());
        $this->assertEquals($shipping['zipcode'], $shippingAddress->getZipcode());
        $this->assertEquals($shipping['city'], $shippingAddress->getCity());
        $this->assertEquals($shipping['phone'], $shippingAddress->getPhoneNumber());
        $this->assertEquals($shipping['vatId'], $shippingAddress->getVatId());
        $this->assertEquals($shipping['additionalAddressLine1'], $shippingAddress->getAdditionalAddressLine1());
        $this->assertEquals($shipping['additionalAddressLine2'], $shippingAddress->getAdditionalAddressLine2());
        $this->assertEquals($shipping['country_state'], $shippingAddress->getCountryStateId());
    }

    public function testChangeEmail()
    {
        $customerId = $this->createCustomerAndLogin();

        $mail = 'test@exapmle.com';
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/email', [], [], [],
            json_encode([
                'email' => $mail,
            ]));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $actualMail = $this->readCustomer($customerId)->getEmail();

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertNull($content);
        $this->assertEquals($mail, $actualMail);
    }

    public function testChangePassword()
    {
        $customerId = $this->createCustomerAndLogin();
        $password = '1234';

        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/password', [], [], [],
            json_encode([
                'password' => $password,
            ]));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $hash = $this->readCustomer($customerId)->getPassword();

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertNull($content);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testChangeProfile()
    {
        $customerId = $this->createCustomerAndLogin();

        $data = [
            'firstname' => 'Test',
            'lastname' => 'User',
            'title' => 'PHD',
            'salutation' => 'Mrs.',
            'birthday' => [
                'year' => 1900,
                'month' => 5,
                'day' => 3,
            ],
        ];
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/profile', [], [], [], json_encode($data));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertNull($content);
        $this->assertEquals($data['firstname'], $customer->getFirstName());
        $this->assertEquals($data['lastname'], $customer->getLastName());
        $this->assertEquals($data['title'], $customer->getTitle());
        $this->assertEquals($data['salutation'], $customer->getSalutation());
        $this->assertEquals(new \DateTime($this->formatBirthday($data['birthday'])), $customer->getBirthday());
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::uuid4()->getHex() . '@example.com';
        $customerId = $this->createCustomer($email, $password);

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/login', [], [], [], json_encode([
            'username' => $email,
            'password' => $password,
        ]));

        return $customerId;
    }

    private function createCustomer(string $email = null, string $password): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'applicationId' => Defaults::APPLICATION,
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
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutation' => 'Mr.',
                'number' => '12345',
            ],
        ], $this->applicationContext);

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

        $this->customerAddressRepository->upsert([$data], $this->applicationContext);

        return $addressId;
    }

    private function readCustomer(string $userID): CustomerBasicStruct
    {
        return $this->customerRepository->readBasic(
            [$userID],
            ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID)
        )->get($userID);
    }

    private function readCustomerAddress(string $addressId): ?CustomerAddressBasicStruct
    {
        return $this->customerAddressRepository->readBasic(
            [$addressId],
            ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID)
        )->get($addressId);
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }

    private function formatBirthday(array $data): ?string
    {
        if (!array_key_exists('year', $data) or
            !array_key_exists('month', $data) or
            !array_key_exists('day', $data)) {
            return null;
        }

        return sprintf(
            '%s-%s-%s',
            (int) $data['year'],
            (int) $data['month'],
            (int) $data['day']
        );
    }
}
