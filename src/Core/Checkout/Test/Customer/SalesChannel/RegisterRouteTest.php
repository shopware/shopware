<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class RegisterRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->addCountriesToSalesChannel([], $this->ids->get('sales-channel'));

        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testRegistration(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $this->getRegistrationData()
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    /**
     * @dataProvider customerBoundToSalesChannelProvider
     */
    public function testRegistrationWithCustomerScope(bool $isCustomerScoped, bool $hasGlobalAccount, bool $hasBoundAccount, bool $requestOnSameSalesChannel, int $expectedStatus): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', $isCustomerScoped);

        if ($hasGlobalAccount || $hasBoundAccount) {
            $boundSalesChannel = $isCustomerScoped && $hasBoundAccount;
            $this->createBoundCustomer($this->ids->get('sales-channel'), $this->getRegistrationData()['email'], $boundSalesChannel);
        }

        $browser = $requestOnSameSalesChannel ? $this->browser : $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $storefrontUrl = $requestOnSameSalesChannel ? 'http://localhost' : 'http://localhost2';

        $browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
            $this->getRegistrationData($storefrontUrl)
        );

        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertEquals($expectedStatus, $browser->getResponse()->getStatusCode());

        if ($expectedStatus === 200) {
            static::assertSame('customer', $response['apiAlias']);
            static::assertArrayNotHasKey('errors', $response);
            static::assertNotEmpty($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

            $browser->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

            $response = json_decode($browser->getResponse()->getContent(), true);

            static::assertArrayHasKey('contextToken', $response);
        } else {
            static::assertNotEmpty($response['errors']);
            static::assertEquals('VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);
        }
    }

    public function testRegistrationWithGivenToken(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $this->getRegistrationData()
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer'
            );

        $customer = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayNotHasKey('errors', $customer);
        static::assertSame('customer', $customer['apiAlias']);
    }

    /**
     * @dataProvider registerWithDomainAndLeadingSlashProvider
     */
    public function testRegistrationWithTrailingSlashUrl(array $domainUrlTest): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel-3'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $domainUrlTest['domain'],
                ],
            ],
        ]);

        $browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
            $this->getRegistrationData($domainUrlTest['expectDomain'])
        );

        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertEquals(200, $browser->getResponse()->getStatusCode());

        static::assertSame('customer', $response['apiAlias']);
        static::assertArrayNotHasKey('errors', $response);
        static::assertNotEmpty($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
            [
                'email' => 'teg-reg@example.com',
                'password' => '12345678',
            ]
        );

        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    public function registerWithDomainAndLeadingSlashProvider()
    {
        return [
            // test without leading slash
            [
                ['domain' => 'http://my-evil-page', 'expectDomain' => 'http://my-evil-page'],
            ],
            // test with leading slash
            [
                ['domain' => 'http://my-evil-page/', 'expectDomain' => 'http://my-evil-page'],
            ],
            // test with double leading slash
            [
                ['domain' => 'http://my-evil-page//', 'expectDomain' => 'http://my-evil-page'],
            ],
        ];
    }

    public function testDoubleOptin(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $this->getRegistrationData()
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);

        $customerId = $response['id'];

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('contextToken', $response);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_IS_INACTIVE', $response['errors'][0]['code']);

        $criteria = new Criteria([$customerId]);
        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->first();

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register-confirm',
                [
                    'hash' => $customer->getHash(),
                    'em' => sha1('teg-reg@example.com'),
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    public function testDoubleOptinGivenTokenIsNotLoggedin(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $this->getRegistrationData()
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer'
            );

        $customer = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $customer);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $customer['errors'][0]['code']);
    }

    public function testDoubleOptinWithHeaderToken(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        // Register
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $this->getRegistrationData()
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        // Validate I am not logged in
        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer'
            );

        $customer = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $customer);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $customer['errors'][0]['code']);

        $customerId = $response['id'];

        $criteria = new Criteria([$customerId]);
        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->first();

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register-confirm',
                [
                    'hash' => $customer->getHash(),
                    'em' => sha1('teg-reg@example.com'),
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer'
            );

        $customer = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayNotHasKey('errors', $customer);
        static::assertSame('customer', $response['apiAlias']);
    }

    public function testRegistrationWithRequestedGroup(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registration' => [
                    'title' => 'test',
                ],
                'registrationSalesChannels' => [['id' => $this->getSalesChannelApiSalesChannelId()]],
            ],
        ], $this->ids->getContext());

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                array_merge($this->getRegistrationData(), ['requestedGroupId' => $this->ids->get('group')])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$response['id']]), $this->ids->getContext())->first();

        static::assertSame($this->ids->get('group'), $customer->getRequestedGroupId());
    }

    public function testContextChangedBetweenRegistration(): void
    {
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', $this->getSalesChannelApiSalesChannelId());

        $bag = new RequestDataBag($this->getRegistrationData());
        $this->getContainer()->get(RegisterRoute::class)->register($bag, $context);

        static::assertNotSame('test', $context->getToken());
    }

    public function customerBoundToSalesChannelProvider(): array
    {
        $isCustomerScoped = true;
        $hasGlobalAccount = true; // Account which has bound_sales_channel_id = null
        $hasBoundAccount = true; // Account which has bound_sales_channel_id not null
        $requestOnSameSalesChannel = true;

        $expectedSuccessStatus = 200;
        $expectedEmailExistedStatus = 400;

        return [
            [$isCustomerScoped, !$hasGlobalAccount, $hasBoundAccount, $requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [$isCustomerScoped, !$hasGlobalAccount, $hasBoundAccount, !$requestOnSameSalesChannel, $expectedSuccessStatus],
            [$isCustomerScoped, $hasGlobalAccount, !$hasBoundAccount, $requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [$isCustomerScoped, $hasGlobalAccount, !$hasBoundAccount, !$requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [$isCustomerScoped, !$hasGlobalAccount, !$hasBoundAccount, $requestOnSameSalesChannel, $expectedSuccessStatus],
            [!$isCustomerScoped, !$hasGlobalAccount, $hasBoundAccount, $requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [!$isCustomerScoped, !$hasGlobalAccount, $hasBoundAccount, !$requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [!$isCustomerScoped, $hasGlobalAccount, !$hasBoundAccount, $requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [!$isCustomerScoped, $hasGlobalAccount, !$hasBoundAccount, !$requestOnSameSalesChannel, $expectedEmailExistedStatus],
            [!$isCustomerScoped, !$hasGlobalAccount, !$hasBoundAccount, $requestOnSameSalesChannel, $expectedSuccessStatus],
        ];
    }

    public function testRegistrationCommercialAccountWithVatIds(): void
    {
        $additionalData = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);
        unset($registrationData['billingAddress']['vatId']);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $registrationData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);
        static::assertSame(['DE123456789'], $response['vatIds']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    public function testRegistrationCommercialAccountWithVatIdsIsEmpty(): void
    {
        $additionalData = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);
        unset($registrationData['billingAddress']['vatId']);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $registrationData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        if ($this->getContainer()->get(SystemConfigService::class)->get('core.loginRegistration.vatIdFieldRequired', $this->getSalesChannelApiSalesChannelId())) {
            static::assertArrayHasKey('errors', $response);
        } else {
            static::assertSame('customer', $response['apiAlias']);
            static::assertNull($response['vatIds']);
            static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

            $this->browser
                ->request(
                    'POST',
                    '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                    [
                        'email' => 'teg-reg@example.com',
                        'password' => '12345678',
                    ]
                );

            $response = json_decode($this->browser->getResponse()->getContent(), true);

            static::assertArrayHasKey('contextToken', $response);
        }
    }

    public function testRegistrationBusinessAccountWithVatIdsNotMatchRegex(): void
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId())]);

        $additionalData = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'abcd',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $registrationData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testRegistrationBusinessAccountWithVatIdsMatchRegex(): void
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId())]);

        $additionalData = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                '123456789',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                $registrationData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('customer', $response['apiAlias']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    private function getRegistrationData(string $storefrontUrl = 'http://localhost'): array
    {
        return [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => '12345678',
            'email' => 'teg-reg@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'storefrontUrl' => $storefrontUrl,
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
    }

    private function createBoundCustomer(string $salesChannelId, string $email, bool $boundSalesChannel = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $email,
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelId,
            'boundSalesChannelId' => $boundSalesChannel ? $salesChannelId : null,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
