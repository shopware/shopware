<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class SalesChannelCustomerControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use MailTemplateTestBehaviour;

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

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var KernelBrowser
     */
    private $browser;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get('serializer');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        $this->countryStateRepository = $this->getContainer()->get('country_state.repository');
        $this->context = Context::createDefaultContext();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->connection = $this->getContainer()->get('Doctrine\DBAL\Connection');

        // reset rules
        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $this->browser = $this->createCustomSalesChannelBrowser(['id' => Defaults::SALES_CHANNEL]);
        $this->assignSalesChannelContext($this->browser);
    }

    public function testLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('sw-context-token', $content);
        static::assertNotEmpty($content['sw-context-token']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer');
        $response = $this->browser->getResponse();

        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);

        static::assertSame($customer['data']['customerNumber'], $content['data']['customerNumber']);
    }

    public function testLoginWithBadCredentials(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(401, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
        static::assertNotEmpty($content['errors']);

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer');
        $response = $this->browser->getResponse();
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

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('sw-context-token', $content);
        static::assertNotEmpty($content['sw-context-token']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer');
        $response = $this->browser->getResponse();

        $content = json_decode($response->getContent(), true);

        $customer = $this->readCustomer($customerId);
        static::assertNull($customer->getLegacyPassword());
        static::assertNull($customer->getLegacyEncoder());
        static::assertTrue(password_verify($password, $customer->getPassword()));

        $customerData = $this->serialize($customer);
        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);

        static::assertSame($customerData['data']['customerNumber'], $content['data']['customerNumber']);
    }

    public function testLogout(): void
    {
        $this->createCustomerAndLogin();
        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/logout');
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer');
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(403, $response->getStatusCode());
        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
    }

    public function testGetCustomerDetail(): void
    {
        $customerId = $this->createCustomerAndLogin();

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer');
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        $customer = $this->serialize($this->readCustomer($customerId));

        static::assertEquals(200, $response->getStatusCode());
        static::assertNotEmpty($content);

        static::assertSame($customer['data']['customerNumber'], $content['data']['customerNumber']);
    }

    public function testGetAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address/' . $addressId);
        $response = $this->browser->getResponse();
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

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address');
        $response = $this->browser->getResponse();
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

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address', $address);
        $response = $this->browser->getResponse();
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

        $this->browser->request('DELETE', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address/' . $addressId);

        $customerAddress = $this->readCustomerAddress($customerId);
        static::assertNull($customerAddress);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $addressId = $this->createCustomerAddress($customerId);
        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address/' . $addressId . '/default-shipping');
        $response = $this->browser->getResponse();
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
        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/address/' . $addressId . '/default-billing');
        $response = $this->browser->getResponse();
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
        $personal = $this->getCustomerRegisterData();

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer', $personal);

        $response = $this->browser->getResponse();
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
        static::assertEquals($personal['salutationId'], $billingAddress->getSalutationId());
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
        static::assertEquals($personal['shippingAddress']['salutationId'], $shippingAddress->getSalutationId());
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
        $mail = Random::getAlphanumericString(32) . '@exapmle.com';

        $payload = [
            'email' => $mail,
            'emailConfirmation' => $mail,
            'password' => 'shopware',
        ];

        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/email', $payload);
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $actualMail = $this->readCustomer($customerId)->getEmail();

        static::assertNull($content);
        static::assertEquals($mail, $actualMail);
    }

    public function testChangePassword(): void
    {
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', 8);

        $customerId = $this->createCustomerAndLogin();
        $password = '12345678';

        $payload = [
            'password' => 'shopware',
            'newPassword' => $password,
            'newPasswordConfirm' => $password,
        ];

        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/password', $payload);
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNull($content);

        $hash = $this->readCustomer($customerId)->getPassword();

        static::assertTrue(password_verify($password, $hash));
    }

    public function testChangePasswordTokenInvalidation(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $oldTokenId = Random::getAlphanumericString(32);

        // insert another token for the same customer to simulate a second active session
        $this->getContainer()
            ->get(SalesChannelContextPersister::class)
            ->save($oldTokenId, [
                'customerId' => $customerId,
            ]);

        $password = '12345678';

        $payload = [
            'password' => 'shopware',
            'newPassword' => $password,
            'newPasswordConfirm' => $password,
        ];

        // change password, the token with $oldTokenId should be revoked
        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/password', $payload);

        // get the invalidated token from the second active session
        $result = $this->connection->createQueryBuilder()
            ->select(['token', 'payload'])
            ->from('sales_channel_api_context')
            ->where('token = :token')
            ->setParameter(':token', $oldTokenId)
            ->execute()
            ->fetch();

        $payload = json_decode($result['payload'], true);

        // customer id in the token should be set to null, which invalidates the token
        static::assertNull($payload['customerId']);
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
        $this->browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer', $data);
        $response = $this->browser->getResponse();
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
            ),
            $customer->getBirthday()
        );
    }

    public function testGetOrdersWithoutLogin(): void
    {
        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/order');
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertEquals('Customer is not logged in.', $content['errors'][0]['detail'] ?? '');
    }

    public function testGetOrders(): void
    {
        $this->createCustomerAndLogin();
        $this->createOrder();

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/order');
        $response = $this->browser->getResponse();
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

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/order?limit=2');
        $response = $this->browser->getResponse();
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

        $this->browser->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/order?limit=2&page=2');
        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($content);
        static::assertCount(1, $content['data']);
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

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
                    'active' => true,
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
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
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

    private function readCustomer(string $customerId): CustomerEntity
    {
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('salutation');
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');

        return $this->customerRepository
            ->search($criteria, Context::createDefaultContext())
            ->get($customerId);
    }

    private function readCustomerAddress(string $addressId): ?CustomerAddressEntity
    {
        $criteria = new Criteria([$addressId]);
        $criteria->addAssociation('salutation');

        return $this->customerAddressRepository
            ->search($criteria, Context::createDefaultContext())
            ->get($addressId);
    }

    private function serialize(CustomerEntity $data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }

    private function formatBirthday(int $day, int $month, int $year): \DateTimeInterface
    {
        return new \DateTime(sprintf(
            '%s-%s-%s',
            $year,
            $month,
            $day
        ));
    }

    private function createOrder(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $salesChannelId = $this->browser->getServerParameter('test-sales-channel-id');

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        // create new cart

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
        $response = $this->browser->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        // add product
        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/product/' . $productId);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        // finish checkout
        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/order');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $order = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);
    }

    private function getCustomerRegisterData(): array
    {
        $personal = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => '12345678',
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

        return $personal;
    }
}
