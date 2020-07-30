<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelContextControllerTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

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
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['shippingMethodId' => $testId]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('The "shipping_method" entity with id "%s" does not exists.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Payment method
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['paymentMethodId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

        static::assertEquals(
            sprintf('The "payment_method" entity with id "%s" does not exists.', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInCustomer(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Billing address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['billingAddressId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_FORBIDDEN, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

        static::assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['shippingAddressId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_FORBIDDEN, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

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
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['billingAddressId' => $testId]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('The "customer_address" entity with id "%s" does not exists.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['shippingAddressId' => $testId]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent(), true);

        static::assertEquals(
            sprintf('The "customer_address" entity with id "%s" does not exists.', $testId),
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
        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['billingAddressId' => $billingId]);

        static::assertSame(Response::HTTP_OK, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['shippingAddressId' => $shippingId]);
        static::assertSame(Response::HTTP_OK, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
    }

    public function testSwitchToNotExistingLanguage(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['languageId' => $id]);

        $request = $this->getSalesChannelBrowser()->getResponse();

        $content = json_decode($request->getContent(), true);

        static::assertSame(Response::HTTP_BAD_REQUEST, $request->getStatusCode(), print_r($content, true));

        static::assertEquals(
            sprintf('The "language" entity with id "%s" does not exists.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testSwitchToValidLanguage(): void
    {
        $id = Defaults::LANGUAGE_SYSTEM;

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['languageId' => $id]);

        $request = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($request->getContent(), true);

        static::assertSame(Response::HTTP_OK, $request->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToValidCurrency(): void
    {
        $id = Defaults::CURRENCY;

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['currencyId' => $id]);

        $request = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($request->getContent(), true);

        static::assertSame(Response::HTTP_OK, $request->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToNotExistingCurrency(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/context', ['currencyId' => $id]);

        $request = $this->getSalesChannelBrowser()->getResponse();

        $content = json_decode($request->getContent(), true);

        static::assertSame(Response::HTTP_BAD_REQUEST, $request->getStatusCode(), print_r($content, true));

        static::assertEquals(
            sprintf('The "currency" entity with id "%s" does not exists.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email = $email ?? Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->assignSalesChannelContext();

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->getSalesChannelBrowser()->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

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
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
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
            'salutationId' => $this->getValidSalutationId(),
            'country' => ['name' => 'Germany'],
        ];

        $this->customerAddressRepository
            ->upsert([$data], Context::createDefaultContext());

        return $addressId;
    }
}
