<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('sales-channel')]
class ContextSwitchRouteTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private EntityRepository $customerRepository;

    private EntityRepository $customerAddressRepository;

    protected function setUp(): void
    {
        $kernel = KernelLifecycleManager::getKernel();
        $this->customerRepository = $kernel->getContainer()->get('customer.repository');
        $this->customerAddressRepository = $kernel->getContainer()->get('customer_address.repository');
    }

    public function testUpdateContextWithNonExistingParameters(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Shipping method
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['shippingMethodId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            sprintf('The "shipping_method" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Payment method
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['paymentMethodId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            sprintf('The "payment_method" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInCustomer(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Billing address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['billingAddressId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_FORBIDDEN, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

        static::assertEquals(
            'Customer is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['shippingAddressId' => $testId]);
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
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
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['billingAddressId' => $testId]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            sprintf('The "customer_address" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()->request('PATCH', '/store-api/context', ['shippingAddressId' => $testId]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getSalesChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            sprintf('The "customer_address" entity with id "%s" does not exist.', $testId),
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
            ->request('PATCH', '/store-api/context', ['billingAddressId' => $billingId]);
        static::assertSame(Response::HTTP_OK, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());

        /*
         * Shipping address
         */
        $this->getSalesChannelBrowser()
            ->request('PATCH', '/store-api/context', ['shippingAddressId' => $shippingId]);
        static::assertSame(Response::HTTP_OK, $this->getSalesChannelBrowser()->getResponse()->getStatusCode());
    }

    public function testSwitchToNotExistingLanguage(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/store-api/context', ['languageId' => $id]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));

        static::assertEquals(
            sprintf('The "language" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testSwitchToValidLanguage(): void
    {
        $id = Defaults::LANGUAGE_SYSTEM;

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/store-api/context', ['languageId' => $id]);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToValidLanguageWithoutDomain(): void
    {
        $id = Uuid::randomHex();

        $browser = $this->createSalesChannelBrowser(null, false, [
            'languageId' => $id,
            'languages' => [
                ['id' => $id],
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'language' => [
                        'id' => $id,
                        'name' => 'Testlanguage',
                        'localeId' => $this->getLocaleIdOfSystemLanguage(),
                        'parentId' => Defaults::LANGUAGE_SYSTEM,
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://someotherdomain',
                ],
            ],
        ]);

        $browser->request('PATCH', '/store-api/context', ['languageId' => Defaults::LANGUAGE_SYSTEM]);

        $response = $browser->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
        static::assertNull($content['redirectUrl']);
    }

    public function testSwitchToValidCurrency(): void
    {
        $id = Defaults::CURRENCY;

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/store-api/context', ['currencyId' => $id]);

        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToNotExistingCurrency(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelBrowser()
            ->request('PATCH', '/store-api/context', ['currencyId' => $id]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));

        static::assertEquals(
            sprintf('The "currency" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    private function createCustomerAndLogin(?string $email = null, string $password = 'shopware'): string
    {
        $email ??= Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($password, $email);

        $this->assignSalesChannelContext();

        $this->getSalesChannelBrowser()->request('POST', '/store-api/account/login', [
            'username' => $email,
            'password' => $password,
        ]);

        $response = $this->getSalesChannelBrowser()->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getSalesChannelBrowser()->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $customerId;
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
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
            'countryId' => $this->getValidCountryId(),
        ];

        $this->customerAddressRepository
            ->upsert([$data], Context::createDefaultContext());

        return $addressId;
    }
}
