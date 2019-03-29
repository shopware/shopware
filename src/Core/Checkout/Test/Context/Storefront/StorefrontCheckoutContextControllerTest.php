<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Context\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class StorefrontCheckoutContextControllerTest extends TestCase
{
    use StorefrontApiTestBehaviour,
        IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    protected function setUp(): void
    {
        $kernel = KernelLifecycleManager::getKernel();
        $this->customerRepository = $kernel->getContainer()->get('customer.repository');
        $this->customerAddressRepository = $kernel->getContainer()->get('customer_address.repository');
        $this->connection = $kernel->getContainer()->get(Connection::class);
    }

    public function testUpdateContextWithNonExistingParameters(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Shipping method
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['shippingMethodId' => $testId]);
        static::assertSame(400, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('Shipping method with id "%s" not found.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Payment method
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['paymentMethodId' => $testId]);
        static::assertSame(400, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('Payment method with id %s not found.', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInCustomer(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Billing address
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['billingAddressId' => $testId]);
        static::assertSame(403, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['shippingAddressId' => $testId]);
        static::assertSame(403, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithLoggedInCustomerAndNonExistingAddresses(): void
    {
        $testId = Uuid::randomHex();

        $this->createCustomerAndLogin();

        /*
         * Billing address
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['billingAddressId' => $testId]);

        static::assertSame(400, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('Customer address with id "%s" not found.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getStorefrontClient()->request('PATCH', '/storefront-api/v1/context', ['shippingAddressId' => $testId]);
        static::assertSame(400, $this->getStorefrontClient()->getResponse()->getStatusCode());
        $content = json_decode($this->getStorefrontClient()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('Customer address with id "%s" not found.', $testId),
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
        $this->getStorefrontClient()
            ->request('PATCH', '/storefront-api/v1/context', ['billingAddressId' => $billingId]);
        static::assertSame(200, $this->getStorefrontClient()->getResponse()->getStatusCode());

        /*
         * Shipping address
         */
        $this->getStorefrontClient()
            ->request('PATCH', '/storefront-api/v1/context', ['shippingAddressId' => $shippingId]);
        static::assertSame(200, $this->getStorefrontClient()->getResponse()->getStatusCode());
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        static::assertSame(200, $this->getStorefrontClient()->getResponse()->getStatusCode());

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
                    'salutationId' => Defaults::SALUTATION_ID_MR,
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => Defaults::SALUTATION_ID_MR,
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

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
            'salutationId' => Defaults::SALUTATION_ID_MRS,
            'country' => ['name' => 'Germany'],
        ];

        $this->customerAddressRepository
            ->upsert([$data], Context::createDefaultContext());

        return $addressId;
    }
}
