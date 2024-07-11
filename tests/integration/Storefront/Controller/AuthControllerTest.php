<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Storefront\AuthTestSubscriber;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\Login\AccountGuestLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @package checkout
 *
 * @internal
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use LineItemTestFixtureBehaviour;
    use StorefrontControllerTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    public function testSessionIsInvalidatedOnLogOut(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', true);

        $browser = $this->login();

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');

        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $session = $this->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotEquals($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotEquals($sessionId, $newSessionId);

        $oldCartExists = $connection->fetchOne('SELECT 1 FROM cart WHERE token = ?', [$contextToken]);
        static::assertFalse($oldCartExists);

        $oldContextExists = $connection->fetchOne('SELECT 1 FROM sales_channel_api_context WHERE token = ?', [$contextToken]);
        static::assertFalse($oldContextExists);
    }

    public function testLogoutWhenSalesChannelIdChangedIfCustomerScopeIsOn(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $browser = $this->login();

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');

        $browser->getResponse();

        $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);

        $browser->request('GET', '/account');

        /** @var RedirectResponse $redirectResponse */
        $redirectResponse = $browser->getResponse();

        static::assertInstanceOf(RedirectResponse::class, $redirectResponse);
        static::assertStringStartsWith('/account/login', $redirectResponse->getTargetUrl());
        static::assertNotEquals($contextToken, $this->getSession()->get('sw-context-token'));
    }

    public function testDoNotLogoutWhenSalesChannelIdChangedIfCustomerScopeIsOff(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', false);

        $browser = $this->login();

        $session = $this->getSession();

        $contextToken = $session->get('sw-context-token');

        $browser->getResponse();

        $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);

        $browser->request('GET', '/account');

        static::assertEquals($contextToken, $this->getSession()->get('sw-context-token'));
    }

    public function testSessionIsInvalidatedOnLogoutAndInvalidateSettingFalse(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $browser = $this->login();

        $sessionCookie = $browser->getCookieJar()->get('session-');
        static::assertNotNull($sessionCookie);

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $session = $this->getSession();

        if ($session->isStarted()) {
            // Close the old session
            $session->save();
        }

        // Set previous session id
        $session->setId($sessionCookie->getValue());
        // Set previous session cookie
        $browser->getCookieJar()->set($sessionCookie);

        // Try opening account page
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/account', []);
        $response = $browser->getResponse();
        $session = $this->getSession();

        // Expect the session to have the same value as the initial session
        static::assertSame($session->getId(), $sessionCookie->getValue());

        // Expect a redirect response, since the old session should be destroyed
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRedirectToAccountPageAfterLogin(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/login', []);
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testSessionIsMigratedOnLogOut(): void
    {
        $browser = $this->login();

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');
        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout');
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/');
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $session = $this->getSession();

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

        $firstTimeLogin = $this->getSession();
        $firstTimeLoginSessionId = $firstTimeLogin->getId();
        $firstTimeLoginContextToken = $firstTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $browser->request('GET', '/account/logout', []);

        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ])
        );

        $secondTimeLogin = $this->getSession();
        $secondTimeLoginSessionId = $secondTimeLogin->getId();
        $secondTimeLoginContextToken = $secondTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotEquals($firstTimeLoginSessionId, $secondTimeLoginSessionId);
        static::assertNotEquals($firstTimeLoginContextToken, $secondTimeLoginContextToken);
    }

    public function testMergedHintIsAdded(): void
    {
        /** @var CustomerEntity|null $customer */
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $contextToken = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->createProductOnDatabase($productId, 'test.123', $context);
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL
        );

        $this->getContainer()->get(SalesChannelContextPersister::class)->save(
            $contextToken,
            [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            TestDefaults::SALES_CHANNEL,
            $customer->getId()
        );

        $cart = new Cart($contextToken);

        $cart->add(new LineItem('productId', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

        $this->getContainer()->get(CartPersister::class)->save($cart, $salesChannelContext);

        $this->getContainer()->get('product.repository')->delete([[
            'id' => $productId,
        ]], $context);

        $request = new Request();
        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $request->setSession($session);
        $this->getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('username', $customer->getEmail());
        $requestDataBag->set('password', 'test12345');

        $salesChannelContextNew = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL
        );

        $this->getContainer()->get(AuthController::class)->login($request, $requestDataBag, $salesChannelContextNew);
        $flashBag = $session->getFlashBag();

        static::assertNotEmpty($infoFlash = $flashBag->get('danger'));
        static::assertEquals($this->getContainer()->get('translator')->trans('checkout.product-not-found', ['%s%' => 'Test product']), $infoFlash[0]);
    }

    public function testAccountLoginPageLoadedHookScriptsAreExecuted(): void
    {
        $this->request('GET', '/account/login', []);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountLoginPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountLoginAlreadyLoggedIn(): void
    {
        $controller = $this->getAuthController();

        /** @var CustomerEntity|null $customer */
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $request = $this->createRequest(
            'frontend.account.login.page',
            [
                'redirectTo' => 'frontend.account.order.single.page',
                'redirectParameters' => ['deepLinkCode' => 'example'],
                'loginError' => false,
                'waitTime' => 5,
            ],
            [
                SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            ]
        );

        $this->getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->login($request, new RequestDataBag($request->attributes->all()), $this->salesChannelContext);

        static::assertEquals(302, $response->getStatusCode());

        static::assertEquals('/account/order/example', $response->getTargetUrl());
    }

    public function testAccountLoginInactiveCustomer(): void
    {
        $controller = $this->getAuthController();

        $this->createCustomer(false, true);

        $request = $this->createRequest(
            'frontend.account.login.page',
            [
                'redirectTo' => 'frontend.account.order.single.page',
                'redirectParameters' => ['deepLinkCode' => 'example'],
                'loginError' => false,
                'waitTime' => 5,
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        $this->getContainer()->get('request_stack')->push($request);

        $response = $controller->login($request, new RequestDataBag($request->attributes->all()), $this->salesChannelContext);

        static::assertEquals(200, $response->getStatusCode());
    }

    public function testGenerateAccountRecovery(): void
    {
        $logger = $this->getContainer()->get('monolog.logger.business_events');
        $handlers = $logger->getHandlers();
        $logger->setHandlers([
            new ExcludeFlowEventHandler($this->getContainer()->get(DoctrineSQLHandler::class), [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ]),
        ]);
        $testSubscriber = new AuthTestSubscriber();

        $this->getContainer()->get('event_dispatcher')->addSubscriber($testSubscriber);

        /** @var CustomerEntity|null $customer */
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $controller = $this->getAuthController($this->getContainer()->get(SendPasswordRecoveryMailRoute::class));

        $request = $this->createRequest('frontend.account.recover.request');

        $data = new RequestDataBag([
            'email' => new RequestDataBag([
                'email' => $customer->getEmail(),
            ]),
        ]);

        $this->getContainer()->get('request_stack')->push($request);

        $response = $controller->generateAccountRecovery($request, $data, $this->salesChannelContext);

        $this->getContainer()->get('event_dispatcher')->removeSubscriber($testSubscriber);

        /** @var FlashBag $flashBag */
        $flashBag = $this->getSession()->getBag('flashes');

        static::assertEquals(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get(StorefrontController::SUCCESS));
        static::assertEquals('/account/recover', $response->headers->get('location') ?? '');

        // excluded events and its mail events should not be logged
        static::assertNotNull(AuthTestSubscriber::$customerRecoveryEvent);
        $originalEvent = AuthTestSubscriber::$customerRecoveryEvent->getName();

        $logCriteria = new Criteria();
        $logCriteria->addFilter(new OrFilter([
            new EqualsFilter('message', $originalEvent),
            new EqualsFilter('context.additionalData.eventName', $originalEvent),
        ]));

        $logEntries = $this->getContainer()->get('log_entry.repository')->search(
            $logCriteria,
            Context::createDefaultContext()
        );

        static::assertCount(0, $logEntries);
        $logger->setHandlers($handlers);
    }

    public function testAccountRecoveryPassword(): void
    {
        $controller = $this->getAuthController();

        $recoveryCreated = $this->createRecovery();

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => $recoveryCreated['hash'],
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        $this->getContainer()->get('request_stack')->push($request);

        $testSubscriber = new AuthTestSubscriber();

        $this->getContainer()->get('event_dispatcher')->addSubscriber($testSubscriber);

        $response = $controller->resetPasswordForm($request, $this->salesChannelContext);

        $this->getContainer()->get('event_dispatcher')->removeSubscriber($testSubscriber);

        static::assertEquals(200, $response->getStatusCode());
        static::assertStringContainsString($recoveryCreated['hash'], (string) $response->getContent());

        static::assertNotNull(AuthTestSubscriber::$renderEvent);
        $parameters = AuthTestSubscriber::$renderEvent->getParameters();

        static::assertNotNull($parameters['page']);
        /** @var AccountRecoverPasswordPage $page */
        $page = $parameters['page'];

        static::assertEquals($recoveryCreated['hash'], $page->getHash());
        static::assertFalse($page->isHashExpired());
    }

    public function testAccountRecoveryPasswordExpired(): void
    {
        $controller = $this->getAuthController();

        $recoveryCreated = $this->createRecovery(true);

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => $recoveryCreated['hash'],
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        $this->getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->salesChannelContext);

        /** @var FlashBag $flashBag */
        $flashBag = $this->getSession()->getBag('flashes');

        static::assertEquals(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertEquals('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordWrongHash(): void
    {
        $controller = $this->getAuthController();

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => 'wrong',
            ]
        );

        $this->getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->salesChannelContext);

        /** @var FlashBag $flashBag */
        $flashBag = $this->getSession()->getBag('flashes');

        static::assertEquals(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertEquals('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordNoHash(): void
    {
        $controller = $this->getAuthController();

        $request = $this->createRequest('frontend.account.recover.password.page');

        $this->getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->salesChannelContext);

        /** @var FlashBag $flashBag */
        $flashBag = $this->getSession()->getBag('flashes');

        static::assertEquals(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertEquals('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordNotMatchingNewPasswords(): void
    {
        $this->request('POST', '/account/recover/password', [
            'password' => [
                'newPassword' => 'kek12345',
                'newPasswordConfirm' => 'kek12345!',
            ],
        ]);

        /** @var FlashBag $flashBag */
        $flashBag = $this->getSession()->getBag('flashes');

        static::assertContains(
            'The passwords you have entered do not match.',
            $flashBag->get('danger')
        );
    }

    public function testAccountGuestLoginPageLoadedHookScriptsAreExecuted(): void
    {
        $this->request('GET', '/account/guest/login', ['redirectTo' => 'foo']);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountGuestLoginPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountGuestLoginPageWithoutRedirectRedirects(): void
    {
        $response = $this->request('GET', '/account/guest/login', []);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/account/login', $response->headers->get('location'));
    }

    private function createProductOnDatabase(string $productId, string $productNumber, Context $context): void
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
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    private function login(): KernelBrowser
    {
        /** @var CustomerEntity|null $customer */
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test12345',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(bool $active = true, bool $doubleOptInReg = false): ?Entity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
                'doubleOptInRegistration' => $doubleOptInReg,
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'test12345',
                'firstName' => 'Max',
                'active' => $active,
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function getAuthController(?AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute = null): AuthController
    {
        $sendPasswordRecoveryMailRoute ??= $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);

        $controller = new AuthController(
            $this->getContainer()->get(AccountLoginPageLoader::class),
            $sendPasswordRecoveryMailRoute,
            $this->getContainer()->get(ResetPasswordRoute::class),
            $this->getContainer()->get(LoginRoute::class),
            $this->createMock(AbstractLogoutRoute::class),
            $this->getContainer()->get(ImitateCustomerRoute::class),
            $this->getContainer()->get(StorefrontCartFacade::class),
            $this->getContainer()->get(AccountRecoverPasswordPageLoader::class)
        );
        $controller->setContainer($this->getContainer());
        $controller->setTwig($this->getContainer()->get('twig'));

        return $controller;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $salesChannelContextOptions
     */
    private function createRequest(string $route, array $params = [], array $salesChannelContextOptions = []): Request
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class)->getDecorated();
        $this->salesChannelContext = $salesChannelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            $salesChannelContextOptions
        );

        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->query->add($params);
        $request->setSession($this->getSession());
        $request->attributes->add([
            '_route' => $route,
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => TestDefaults::SALES_CHANNEL,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->salesChannelContext,
            RequestTransformer::STOREFRONT_URL => 'http://localhost',
        ]);

        return $request;
    }

    /**
     * @return array{customer: CustomerEntity, hash: string, hashId: string}
     */
    private function createRecovery(bool $expired = false): array
    {
        /** @var CustomerEntity|null $customer */
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $hash = Random::getAlphanumericString(32);
        $hashId = Uuid::randomHex();

        $this->getContainer()->get('customer_recovery.repository')->create([
            [
                'id' => $hashId,
                'customerId' => $customer->getId(),
                'hash' => $hash,
            ],
        ], Context::createDefaultContext());

        if ($expired) {
            $this->getContainer()->get(Connection::class)->update(
                'customer_recovery',
                [
                    'created_at' => (new \DateTime())->sub(new \DateInterval('PT3H'))->format(
                        Defaults::STORAGE_DATE_TIME_FORMAT
                    ),
                ],
                [
                    'id' => Uuid::fromHexToBytes($hashId),
                ]
            );
        }

        return ['customer' => $customer, 'hash' => $hash, 'hashId' => $hashId];
    }
}
