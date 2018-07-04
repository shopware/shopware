<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Context\Storefront;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class CheckoutContextControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $customerAddressRepository;

    protected function setUp()
    {
        parent::setUp();

        self::bootKernel();

        $this->customerRepository = self::$container->get('customer.repository');
        $this->customerAddressRepository = self::$container->get('customer_address.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testUpdateContextWithNonExistingParameters(): void
    {
        $testId = Uuid::uuid4()->getHex();

        /*
         * Shipping method
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['shippingMethodId' => $testId]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            sprintf('Shipping method with id %s not found', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Payment method
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['paymentMethodId' => $testId]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            sprintf('Payment method with id %s not found', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInCustomer(): void
    {
        $testId = Uuid::uuid4()->getHex();

        /*
         * Billing address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['billingAddressId' => $testId]);
        $this->assertSame(403, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['shippingAddressId' => $testId]);
        $this->assertSame(403, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithLoggedInCustomerAndNonExistingAddresses(): void
    {
        $testId = Uuid::uuid4()->getHex();

        $this->createCustomerAndLogin();

        /*
         * Billing address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['billingAddressId' => $testId]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            sprintf('Customer address with id %s not found', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['shippingAddressId' => $testId]);
        $this->assertSame(400, $this->storefrontApiClient->getResponse()->getStatusCode());
        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $this->assertEquals(
            sprintf('Customer address with id %s not found', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithLoggedInCustomer(): void
    {
        $customerId = $this->createCustomerAndLogin();
        $billingId = $this->createCustomerAddress($customerId);
        $shippingId = $this->createCustomerAddress($customerId);

        /*
         * Billing address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['billingAddressId' => $billingId]);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());

        /*
         * Shipping address
         */
        $this->storefrontApiClient->request('PUT', '/storefront-api/context', ['shippingAddressId' => $shippingId]);
        $this->assertSame(200, $this->storefrontApiClient->getResponse()->getStatusCode());
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
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutation' => 'Mr.',
                'number' => '12345',
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

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

        $this->customerAddressRepository->upsert([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        return $addressId;
    }
}
