<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AuthControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use LineItemTestFixtureBehaviour;

    public function testSessionIsInvalidatedOnLogOut(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', true);

        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');

        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $session = $browser->getRequest()->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotEquals($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotEquals($sessionId, $newSessionId);

        $oldCartExists = $connection->fetchColumn('SELECT 1 FROM cart WHERE token = ?', [$contextToken]);
        static::assertFalse($oldCartExists);

        $oldContextExists = $connection->fetchColumn('SELECT 1 FROM sales_channel_api_context WHERE token = ?', [$contextToken]);
        static::assertFalse($oldContextExists);
    }

    public function testLogoutWhenSalesChannelIdChangedIfCustomerScopeIsOn(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');

        static::assertEquals($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID), $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, Defaults::SALES_CHANNEL);

        $browser->request('GET', '/account');

        /** @var RedirectResponse $redirectResponse */
        $redirectResponse = $browser->getResponse();

        static::assertInstanceOf(RedirectResponse::class, $redirectResponse);
        static::assertStringStartsWith('/account/login', $redirectResponse->getTargetUrl());
        static::assertNotEquals($contextToken, $browser->getRequest()->getSession()->get('sw-context-token'));
    }

    public function testDoNotLogoutWhenSalesChannelIdChangedIfCustomerScopeIsOff(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', false);

        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');

        static::assertEquals($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID), $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, Defaults::SALES_CHANNEL);

        $browser->request('GET', '/account');

        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(AccountOverviewPage::class, $response->getData()['page']);
        static::assertEquals($contextToken, $browser->getRequest()->getSession()->get('sw-context-token'));
    }

    public function testSessionIsInvalidatedOnLogoutAndInvalidateSettingFalse(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $browser = $this->login();

        $sessionCookie = $browser->getCookieJar()->get('session-');

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());
        $session = $browser->getRequest()->getSession();

        // Close the old session
        $session->save();
        // Set previous session id
        $session->setId($sessionCookie->getValue());
        // Set previous session cookie
        $browser->getCookieJar()->set($sessionCookie);

        // Try opening account page
        $browser->request('GET', $_SERVER['APP_URL'] . '/account', []);
        $response = $browser->getResponse();
        $session = $browser->getRequest()->getSession();

        // Expect the session to have the same value as the initial session
        static::assertSame($session->getId(), $sessionCookie->getValue());

        // Expect a redirect response, since the old session should be destroyed
        static::assertSame(302, $response->getStatusCode(), $response->getContent());
    }

    public function testRedirectToAccountPageAfterLogin(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/login', []);
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testSessionIsMigratedOnLogOut(): void
    {
        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');
        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $session = $browser->getRequest()->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotEquals($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotEquals($sessionId, $newSessionId);
    }

    public function testOneUserUseOneContextAcrossSessions(): void
    {
        $browser = $this->login();

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $firstTimeLogin = $browser->getRequest()->getSession();
        $firstTimeLoginSessionId = $firstTimeLogin->getId();
        $firstTimeLoginContextToken = $firstTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $browser->request('GET', '/account/logout', []);

        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => 'test@example.com',
                'password' => 'test',
            ])
        );

        $secondTimeLogin = $browser->getRequest()->getSession();
        $secondTimeLoginSessionId = $secondTimeLogin->getId();
        $secondTimeLoginContextToken = $secondTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotEquals($firstTimeLoginSessionId, $secondTimeLoginSessionId);
        static::assertEquals($firstTimeLoginContextToken, $secondTimeLoginContextToken);
    }

    public function testMergedHintIsAdded(): void
    {
        $customer = $this->createCustomer();
        $contextToken = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->createProductOnDatabase($productId, 'test.123', $context);
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            Defaults::SALES_CHANNEL
        );

        $this->getContainer()->get(SalesChannelContextPersister::class)->save(
            $contextToken,
            [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            Defaults::SALES_CHANNEL,
            $customer->getId()
        );

        $cart = new Cart('sales-channel', $contextToken);

        $cart->add(new LineItem('productId', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

        $this->getContainer()->get(CartPersister::class)->save($cart, $salesChannelContext);

        $this->getContainer()->get('product.repository')->delete([[
            'id' => $productId,
        ]], $context);

        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $this->getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('username', $customer->getEmail());
        $requestDataBag->set('password', 'test');

        $salesChannelContextNew = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL
        );

        $this->getContainer()->get(AuthController::class)->login($request, $requestDataBag, $salesChannelContextNew);
        $flashBag = $this->getContainer()->get('session')->getFlashBag();

        static::assertNotEmpty($infoFlash = $flashBag->get('warning'));
        static::assertEquals($this->getContainer()->get('translator')->trans('checkout.product-not-found', ['%s%' => 'Test product']), $infoFlash[0]);
    }

    private function createProductOnDatabase(string $productId, string $productNumber, $context): void
    {
        $taxId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => $productNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }
}
