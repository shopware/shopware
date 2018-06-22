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
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            'firstName' => 'Example',
            'lastName' => 'Test',
            'street' => 'Coastal Highway 72',
            'city' => 'New York',
            'zipcode' => '12749',
            'countryId' => Defaults::COUNTRY,
            'company' => 'Shopware AG',
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer/address', [], [], [], json_encode($address));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);

        $this->assertTrue(Uuid::isValid($content['data']));

        $customerAddress = $this->readCustomerAddress($content['data']);

        $this->assertEquals($customerId, $customerAddress->getCustomerId());
        $this->assertEquals($address['countryId'], $customerAddress->getCountryId());
        $this->assertEquals($address['salutation'], $customerAddress->getSalutation());
        $this->assertEquals($address['firstName'], $customerAddress->getFirstName());
        $this->assertEquals($address['lastName'], $customerAddress->getLastName());
        $this->assertEquals($address['street'], $customerAddress->getStreet());
        $this->assertEquals($address['zipcode'], $customerAddress->getZipcode());
        $this->assertEquals($address['city'], $customerAddress->getCity());
    }

    public function testDeleteAddress()
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $customerAddress = $this->readCustomerAddress($addressId);
        $this->assertInstanceOf(CustomerAddressStruct::class, $customerAddress);
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
            'billingCountryState' => $countryStateId,
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
            'shippingCountryState' => $countryStateId,
        ];

        $this->storefrontApiClient->request('POST', '/storefront-api/customer', [], [], [],
            json_encode(
                array_merge($personal, $billing, $shipping)
            )
        );

        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($content);

        $uuid = $content['data'];
        $this->assertTrue(Uuid::isValid($uuid));

        $customer = $this->readCustomer(Uuid::optimize($uuid));

        // verify personal data
        $this->assertEquals($personal['salutation'], $customer->getSalutation());
        $this->assertEquals($personal['firstName'], $customer->getFirstName());
        $this->assertEquals($personal['lastName'], $customer->getLastName());
        $this->assertTrue(password_verify($personal['password'], $customer->getPassword()));
        $this->assertEquals($personal['email'], $customer->getEmail());
        $this->assertEquals($personal['title'], $customer->getTitle());
        $this->assertEquals($personal['active'], $customer->getActive());
        $this->assertEquals(
            $this->formatBirthday(
                $personal['birthdayDay'],
                $personal['birthdayMonth'],
                $personal['birthdayYear']
            ),
            $customer->getBirthday()
        );

        // verify billing address
        $billingAddress = $customer->getDefaultBillingAddress();

        $this->assertEquals($billing['billingCountry'], $billingAddress->getCountryId());
        $this->assertEquals($personal['salutation'], $billingAddress->getSalutation());
        $this->assertEquals($personal['firstName'], $billingAddress->getFirstName());
        $this->assertEquals($personal['lastName'], $billingAddress->getLastName());
        $this->assertEquals($billing['billingStreet'], $billingAddress->getStreet());
        $this->assertEquals($billing['billingZipcode'], $billingAddress->getZipcode());
        $this->assertEquals($billing['billingCity'], $billingAddress->getCity());
        $this->assertEquals($billing['billingPhone'], $billingAddress->getPhoneNumber());
        $this->assertEquals($billing['billingVatId'], $billingAddress->getVatId());
        $this->assertEquals($billing['billingAdditionalAddressLine1'], $billingAddress->getAdditionalAddressLine1());
        $this->assertEquals($billing['billingAdditionalAddressLine2'], $billingAddress->getAdditionalAddressLine2());
        $this->assertEquals($billing['billingCountryState'], $billingAddress->getCountryStateId());

        // verify shipping address
        $shippingAddress = $customer->getDefaultShippingAddress();

        $this->assertEquals($shipping['shippingCountry'], $shippingAddress->getCountryId());
        $this->assertEquals($shipping['shippingSalutation'], $shippingAddress->getSalutation());
        $this->assertEquals($shipping['shippingFirstName'], $shippingAddress->getFirstName());
        $this->assertEquals($shipping['shippingLastName'], $shippingAddress->getLastName());
        $this->assertEquals($shipping['shippingStreet'], $shippingAddress->getStreet());
        $this->assertEquals($shipping['shippingZipcode'], $shippingAddress->getZipcode());
        $this->assertEquals($shipping['shippingCity'], $shippingAddress->getCity());
        $this->assertEquals($shipping['shippingPhone'], $shippingAddress->getPhoneNumber());
        $this->assertEquals($shipping['shippingAdditionalAddressLine1'], $shippingAddress->getAdditionalAddressLine1());
        $this->assertEquals($shipping['shippingAdditionalAddressLine2'], $shippingAddress->getAdditionalAddressLine2());
        $this->assertEquals($shipping['shippingCountryState'], $shippingAddress->getCountryStateId());
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
            'firstName' => 'Test',
            'lastName' => 'User',
            'title' => 'PHD',
            'salutation' => 'Mrs.',
            'birthdayYear' => 1900,
            'birthdayMonth' => 5,
            'birthdayDay' => 3,
        ];
        $this->storefrontApiClient->request('PUT', '/storefront-api/customer/profile', [], [], [], json_encode($data));
        $response = $this->storefrontApiClient->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertNull($content);
        $this->assertEquals($data['firstName'], $customer->getFirstName());
        $this->assertEquals($data['lastName'], $customer->getLastName());
        $this->assertEquals($data['title'], $customer->getTitle());
        $this->assertEquals($data['salutation'], $customer->getSalutation());
        $this->assertEquals(
            $this->formatBirthday(
                $data['birthdayDay'],
                $data['birthdayMonth'],
                $data['birthdayYear']
            ), $customer->getBirthday()
        );
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
                'touchpointId' => Defaults::TOUCHPOINT,
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
            Context:: createDefaultContext(Defaults::TENANT_ID)
        )->get($userID);
    }

    private function readCustomerAddress(string $addressId): ?CustomerAddressStruct
    {
        return $this->customerAddressRepository->read(
            new ReadCriteria([$addressId]),
            Context:: createDefaultContext(Defaults::TENANT_ID)
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
}
