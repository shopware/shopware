<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class LogoutRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

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
                '/store-api/account/login',
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $currentCustomerToken = $response['contextToken'];

        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

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

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

        static::assertFalse($customerIdWithOldToken);

        $newCustomerContextToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT token FROM sales_channel_api_context WHERE customer_id = ?', [$currentCustomerId]);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $currentCustomerToken = $response['contextToken'];

        $currentCustomerId = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

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

        $customerIdWithOldToken = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);

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
            Defaults::SALES_CHANNEL,
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);
        $loginResponse = $this->getContainer()->get(LoginRoute::class)->login($request, $salesChannelContext);

        $customer = new CustomerEntity();
        $customer->setGuest(false);
        $salesChannelContext->assign([
            'token' => $loginResponse->getToken(),
            'customer' => $customer,
        ]);

        $logoutResponse = $this->getContainer()->get(LogoutRoute::class)->logout($salesChannelContext, new RequestDataBag());

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
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, []);

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);
        $login = $this->getContainer()
            ->get(LoginRoute::class)
            ->login($request, $context);

        $customer = new CustomerEntity();
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
            ->fetchAll('SELECT * FROM sales_channel_api_context WHERE token = :token', ['token' => $login->getToken()]);

        static::assertEmpty($exists);
    }

    public function testValidLogoutAsGuest(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email, true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel')));

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

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                [],
                [],
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
    }
}
