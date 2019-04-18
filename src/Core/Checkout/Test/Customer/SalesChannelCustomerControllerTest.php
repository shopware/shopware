<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class SalesChannelCustomerControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

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

        // reset rules
        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
    }

    public function testLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('x-sw-context-token', $content);
        static::assertNotEmpty($content['x-sw-context-token']);

        $this->getSalesChannelClient()->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer');
        $response = $this->getSalesChannelClient()->getResponse();

        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customer, $content);
    }

    public function testLoginWithBadCredentials(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(401, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);
    }

    public function testLoginWithLegacyPassword(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer('dummy', $email);
        $this->customerRepository->update([
            [
                'id' => $customerId,
                'legacyEncoder' => 'Md5',
                'legacyPassword' => md5($password),
            ],
        ], $this->context);

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('x-sw-context-token', $content);
        static::assertNotEmpty($content['x-sw-context-token']);

        $this->getSalesChannelClient()->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer');
        $response = $this->getSalesChannelClient()->getResponse();

        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);
        static::assertNull($customer->getLegacyPassword());
        static::assertNull($customer->getLegacyEncoder());
        static::assertTrue(password_verify($password, $customer->getPassword()));

        $customerData = $this->serialize($customer);
        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertEquals($customerData, $content);
    }

    public function testLogout(): void
    {
        $this->createCustomerAndLogin();
        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/logout');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
    }

    public function testGetCustomerDetail(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/address/' . $addressId);
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/address');
        $response = $this->getSalesChannelClient()->getResponse();
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
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Example',
            'lastName' => 'Test',
            'street' => 'Coastal Highway 72',
            'city' => 'New York',
            'zipcode' => '12749',
            'countryId' => $this->getValidCountryId(),
            'company' => 'Shopware AG',
        ];

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/address', $address);
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);

        static::assertTrue(Uuid::isValid($content['data']));

        $customerAddress = $this->readCustomerAddress($content['data']);

        static::assertEquals($customerId, $customerAddress->getCustomerId());
        static::assertEquals($address['countryId'], $customerAddress->getCountryId());
        static::assertEquals($address['salutationId'], $customerAddress->getSalutation()->getId());
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

        $this->getSalesChannelClient()->request('DELETE', '/sales-channel-api/v1/customer/address/' . $addressId);

        $customerAddress = $this->readCustomerAddress($customerId);
        static::assertNull($customerAddress);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/customer/address/' . $addressId . '/default-shipping');
        $response = $this->getSalesChannelClient()->getResponse();
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
        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/customer/address/' . $addressId . '/default-billing');
        $response = $this->getSalesChannelClient()->getResponse();
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
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => 'test',
            'email' => Uuid::randomHex() . '@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
                'phoneNumber' => '0123456789',
                'vatId' => 'DE999999999',
                'additionalAddressLine1' => 'Additional address line 1',
                'additionalAddressLine2' => 'Additional address line 2',
            ],
            'shippingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
                'phoneNumber' => '987654321',
                'additionalAddressLine1' => 'Additional address line 01',
                'additionalAddressLine2' => 'Additional address line 02',
            ],
        ];

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer', $personal);

        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertNotEmpty($content);

        $uuid = $content['data'];
        static::assertTrue(Uuid::isValid($uuid));

        $customer = $this->readCustomer($uuid);

        // verify personal data
        static::assertEquals($personal['salutationId'], $customer->getSalutation()->getId());
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

        static::assertEquals($personal['billingAddress']['countryId'], $billingAddress->getCountryId());
        static::assertEquals($personal['salutationId'], $billingAddress->getSalutation()->getId());
        static::assertEquals($personal['firstName'], $billingAddress->getFirstName());
        static::assertEquals($personal['lastName'], $billingAddress->getLastName());
        static::assertEquals($personal['billingAddress']['street'], $billingAddress->getStreet());
        static::assertEquals($personal['billingAddress']['zipcode'], $billingAddress->getZipcode());
        static::assertEquals($personal['billingAddress']['city'], $billingAddress->getCity());
        static::assertEquals($personal['billingAddress']['phoneNumber'], $billingAddress->getPhoneNumber());
        static::assertEquals($personal['billingAddress']['vatId'], $billingAddress->getVatId());
        static::assertEquals($personal['billingAddress']['additionalAddressLine1'], $billingAddress->getAdditionalAddressLine1());
        static::assertEquals($personal['billingAddress']['additionalAddressLine2'], $billingAddress->getAdditionalAddressLine2());

        // verify shipping address
        $shippingAddress = $customer->getDefaultShippingAddress();

        static::assertEquals($personal['shippingAddress']['countryId'], $shippingAddress->getCountryId());
        static::assertEquals($personal['shippingAddress']['salutationId'], $shippingAddress->getSalutation()->getId());
        static::assertEquals($personal['shippingAddress']['firstName'], $shippingAddress->getFirstName());
        static::assertEquals($personal['shippingAddress']['lastName'], $shippingAddress->getLastName());
        static::assertEquals($personal['shippingAddress']['street'], $shippingAddress->getStreet());
        static::assertEquals($personal['shippingAddress']['zipcode'], $shippingAddress->getZipcode());
        static::assertEquals($personal['shippingAddress']['city'], $shippingAddress->getCity());
        static::assertEquals($personal['shippingAddress']['phoneNumber'], $shippingAddress->getPhoneNumber());
        static::assertEquals($personal['shippingAddress']['additionalAddressLine1'], $shippingAddress->getAdditionalAddressLine1());
        static::assertEquals($personal['shippingAddress']['additionalAddressLine2'], $shippingAddress->getAdditionalAddressLine2());
    }

    public function testChangeEmail(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $mail = 'test@exapmle.com';
        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/customer/email', ['email' => $mail]);
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/customer/password', ['password' => $password]);
        $response = $this->getSalesChannelClient()->getResponse();
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
            'salutationId' => $this->getValidSalutationId(),
            'birthdayYear' => 1900,
            'birthdayMonth' => 5,
            'birthdayDay' => 3,
        ];
        $this->getSalesChannelClient()->request('PATCH', '/sales-channel-api/v1/customer', $data);
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);
        static::assertEquals($data['firstName'], $customer->getFirstName());
        static::assertEquals($data['lastName'], $customer->getLastName());
        static::assertEquals($data['title'], $customer->getTitle());
        static::assertEquals($data['salutationId'], $customer->getSalutation()->getId());
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
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/order');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertEquals('Customer is not logged in.', $content['errors'][0]['detail'] ?? '');
    }

    public function testGetOrders(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/order');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    public function testGetOrdersWithLimit(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();
        $this->createOrder();
        $this->createOrder();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/order?limit=2');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/customer/order?limit=2&page=2');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->getSalesChannelClient()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->getSalesChannelClient()->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $customerId;
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

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
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $this->context);

        return $customerId;
    }

    private function createCustomerAddress(string $customerId): string
    {
        $addressId = Uuid::randomHex();
        $data = [
            'id' => $addressId,
            'customerId' => $customerId,
            'firstName' => 'Test',
            'lastName' => 'User',
            'street' => 'Musterstraße 2',
            'city' => 'Cologne',
            'zipcode' => '89563',
            'salutationId' => $this->getValidSalutationId(),
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
            'data' => $decoded,
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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        // create new cart
        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/checkout/cart');
        $response = $this->getSalesChannelClient()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        // add product
        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/checkout/cart/product/' . $productId);
        static::assertSame(200, $this->getSalesChannelClient()->getResponse()->getStatusCode(), $this->getSalesChannelClient()->getResponse()->getContent());

        // finish checkout
        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/checkout/order');
        static::assertSame(200, $this->getSalesChannelClient()->getResponse()->getStatusCode(), $this->getSalesChannelClient()->getResponse()->getContent());

        $order = json_decode($this->getSalesChannelClient()->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);
    }
}
