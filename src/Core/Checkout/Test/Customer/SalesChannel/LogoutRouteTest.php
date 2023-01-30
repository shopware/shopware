<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('customer-order')]
class LogoutRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
                [
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
                [
                    'replace-token' => true,
                ],
                [],
                [
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                [],
                [],
                [
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testLoggedOutUpdateCustomerContextWithReplaceTokenParameter(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        $currentCustomerToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '';
        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchOne('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentCustomerToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
                [
                    'replace-token' => true,
                ],
                [],
                [
                ]
            );

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchOne('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        static::assertFalse($customerIdWithOldToken);

        $newCustomerContextToken = $this->getContainer()->get(Connection::class)->fetchOne('SELECT token FROM sales_channel_api_context WHERE customer_id = ?', [$currentCustomerId]);

        static::assertNotEmpty($newCustomerContextToken);
        static::assertNotEquals($currentCustomerToken, $newCustomerContextToken);
    }

    public function testLoggedOutKeepCustomerContextWithoutReplaceTokenParameter(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        $currentCustomerToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '';
        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchOne('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentCustomerToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
                [],
                [],
                [
                ]
            );

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchOne('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        static::assertEquals($currentCustomerId, $customerIdWithOldToken);
    }

    public function testLogoutRouteReturnContextTokenResponse(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $contextToken = Random::getAlphanumericString(32);

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);
        $loginResponse = $this->getContainer()->get(LoginRoute::class)->login($request, $salesChannelContext);

        $customerId = $this->createCustomer();
        $customer = $this->getContainer()
            ->get('customer.repository')
            ->search(new Criteria(), Context::createDefaultContext())
            ->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $customer->setGuest(false);
        $salesChannelContext->assign([
            'token' => $loginResponse->getToken(),
            'customer' => $customer,
        ]);

        $logoutResponse = $this->getContainer()->get(LogoutRoute::class)->logout(
            $salesChannelContext,
            new RequestDataBag()
        );

        static::assertInstanceOf(ContextTokenResponse::class, $logoutResponse);
        static::assertNotEquals($loginResponse->getToken(), $logoutResponse->getToken());
    }

    public function testLogoutForcedForGuestAccounts(): void
    {
        $config = $this->getContainer()->get(SystemConfigService::class);
        $config->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);
        $login = $this->getContainer()
            ->get(LoginRoute::class)
            ->login($request, $context);

        $customerId = $this->createCustomer();
        $customer = $this->getContainer()
            ->get('customer.repository')
            ->search(new Criteria(), Context::createDefaultContext())
            ->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $customer->setGuest(true);
        $context->assign([
            'token' => $login->getToken(),
            'customer' => $customer,
        ]);

        $logout = $this->getContainer()
            ->get(LogoutRoute::class)
            ->logout($context, $request);

        static::assertInstanceOf(ContextTokenResponse::class, $logout);
        static::assertEquals($login->getToken(), $logout->getToken());

        $exists = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM sales_channel_api_context WHERE token = :token', ['token' => $login->getToken()]);

        static::assertEmpty($exists);
    }

    public function testValidLogoutAsGuest(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email, true);
        $this->browser->setServerParameter(
            'HTTP_SW_CONTEXT_TOKEN',
            $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'))
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
                [
                    'replace-token' => true,
                ],
                [],
                [
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        static::assertSame(
            200,
            $this->browser->getResponse()->getStatusCode(),
            $this->browser->getResponse()->getContent()
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                [],
                [],
                [
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }
}
