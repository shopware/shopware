<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LogoutRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

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
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/logout',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testValidLogout(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/logout',
                [],
                [],
                [
                ]
            );

        Feature::skipTestIfActive('FEATURE_NEXT_10058', $this);

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer',
                [],
                [],
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testLoggedOutUpdateCustomerContextWithReplaceTokenParameter(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $currentCustomerToken = $response['contextToken'];

        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentCustomerToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/logout',
                [
                    'replace-token' => true,
                ],
                [],
                [
                ]
            );

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        static::assertFalse($customerIdWithOldToken);

        $newCustomerContextToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT token FROM sales_channel_api_context WHERE customer_id = ?', [$currentCustomerId]);

        static::assertNotEmpty($newCustomerContextToken);
        static::assertNotEquals($currentCustomerToken, $newCustomerContextToken);
    }

    public function testLoggedOutKeepCustomerContextWithoutReplaceTokenParameter(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $currentCustomerToken = $response['contextToken'];

        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentCustomerToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/logout',
                [],
                [],
                [
                ]
            );

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        static::assertEquals($currentCustomerId, $customerIdWithOldToken);
    }

    public function testLogoutRouteReturnContextTokenResponse(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $contextToken = Random::getAlphanumericString(32);

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            Defaults::SALES_CHANNEL,
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);
        $loginResponse = $this->getContainer()->get(LoginRoute::class)->login($request, $salesChannelContext);

        $salesChannelContext->assign([
            'token' => $loginResponse->getToken(),
            'customer' => new CustomerEntity(),
        ]);

        $logoutResponse = $this->getContainer()->get(LogoutRoute::class)->logout($salesChannelContext);

        static::assertInstanceOf(ContextTokenResponse::class, $logoutResponse);
        static::assertNotEquals($loginResponse->getToken(), $logoutResponse->getToken());
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
                    'countryId' => $this->getValidCountryId(),
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
        ], $this->ids->context);

        return $customerId;
    }
}
