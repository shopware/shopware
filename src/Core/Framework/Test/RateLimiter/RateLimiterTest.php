<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Controller\AuthController as AdminAuthController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Controller\FormController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Contracts\Translation\TranslatorInterface;

class RateLimiterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;
    use OrderFixture;
    use StorefrontControllerTestBehaviour;

    private Context $context;

    private TestDataCollection $ids;

    private KernelBrowser $browser;

    private ?AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private SalesChannelContext $salesChannelContext;

    private ?TranslatorInterface $translator;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_13795', $this);
        DisableRateLimiterCompilerPass::disableNoLimit();

        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection($this->context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $this->salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class)->getDecorated();
        $this->salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->clearCache();

        $this->translator = $this->getContainer()->get('translator');
    }

    public function tearDown(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public function testRateLimitLoginRoute(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'wrongPassword';
        $this->createCustomer('shopware', $email);

        for ($i = 0; $i <= 10; ++$i) {
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
            static::assertArrayHasKey('errors', $response);

            if ($i >= 10) {
                static::assertEquals(429, $response['errors'][0]['status']);
                static::assertEquals('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertEquals(401, $response['errors'][0]['status']);
                static::assertEquals('Unauthorized', $response['errors'][0]['title']);
            }
        }
    }

    public function testResetRateLimitLoginRoute(): void
    {
        $route = new LoginRoute(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('customer.repository'),
            $this->getContainer()->get(LegacyPasswordVerifier::class),
            $this->getContainer()->get(SalesChannelContextRestorer::class),
            $this->getContainer()->get('request_stack'),
            $this->mockResetLimiter([
                RateLimiter::LOGIN_ROUTE => 1,
            ])
        );

        $this->createCustomer('shopware', 'loginTest@example.com');

        $this->getContainer()->get('request_stack')->push(new Request([
            'email' => 'loginTest@example.com',
            'password' => 'shopware',
        ]));

        $route->login(new RequestDataBag([
            'email' => 'loginTest@example.com',
            'password' => 'shopware',
        ]), $this->salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL));
    }

    public function testRateLimitOauth(): void
    {
        for ($i = 0; $i <= 10; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'admin',
                        'password' => 'bla',
                    ]
                );

            $response = json_decode($this->browser->getResponse()->getContent(), true);
            static::assertArrayHasKey('errors', $response);

            if ($i >= 10) {
                static::assertEquals(429, $response['errors'][0]['status']);
                static::assertEquals('FRAMEWORK__AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertEquals(400, $response['errors'][0]['status']);
                static::assertEquals(10, $response['errors'][0]['code']);
            }
        }
    }

    public function testResetRateLimitOauth(): void
    {
        $psrFactory = $this->createMock(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn($this->createMock(ServerRequest::class));
        $psrFactory->method('createResponse')->willReturn($this->createMock(Response::class));

        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->method('respondToAccessTokenRequest')->willReturn(new Response());

        $controller = new AdminAuthController(
            $authorizationServer,
            $psrFactory,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
            ])
        );

        $controller->token(new Request());
    }

    public function testRateLimitContactForm(): void
    {
        for ($i = 0; $i <= 3; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/contact-form',
                    [
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'email' => 'test@example.com',
                        'phone' => '+49123456789',
                        'subject' => 'Test contact request',
                        'comment' => 'Hello, this is my test request.',
                    ]
                );

            $response = json_decode($this->browser->getResponse()->getContent(), true);

            if ($i >= 3) {
                static::assertArrayHasKey('errors', $response);
                static::assertEquals(429, $response['errors'][0]['status']);
                static::assertEquals('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertEquals(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitUserRecovery(): void
    {
        for ($i = 0; $i <= 3; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/_action/user/user-recovery',
                    [
                        'email' => 'test@example.com',
                    ]
                );

            $response = json_decode($this->browser->getResponse()->getContent(), true);

            if ($i >= 3) {
                static::assertArrayHasKey('errors', $response);
                static::assertEquals(429, $response['errors'][0]['status']);
                static::assertEquals('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertEquals(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testResetRateLimtitUserRecovery(): void
    {
        $recoveryService = $this->createMock(UserRecoveryService::class);
        $recoveryService->method('getUserByHash')->willReturn($this->createMock(UserEntity::class));
        $recoveryService->method('updatePassword')->willReturn(true);

        $controller = new UserRecoveryController(
            $recoveryService,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
                RateLimiter::USER_RECOVERY => 1,
            ]),
        );

        $controller->updateUserPassword(new Request(), $this->context);
    }

    public function testAccountOrderRateLimit(): void
    {
        $order = $this->createCustomerWithOrder();

        for ($i = 0; $i <= 10; ++$i) {
            $this->browser->request(
                'POST',
                '/account/order/' . $order->getDeepLinkCode(),
                $this->tokenize('frontend.account.order.single.page', [
                    'email' => 'orderTest@example.com',
                    'zipcode' => 'wrong',
                ])
            );

            /** @var RedirectResponse $response */
            $response = $this->browser->getResponse();

            $waitTime = $i >= 10 ? $this->queryFromString($response->getTargetUrl(), 'waitTime') : 0;

            $this->browser->request(
                'GET',
                $response->getTargetUrl()
            );

            /** @var StorefrontResponse $targetResponse */
            $targetResponse = $this->browser->getResponse();

            $contentReturn = $targetResponse->getContent();
            $crawler = new Crawler();
            $crawler->addHtmlContent($contentReturn);

            $errorContent = $crawler->filterXPath('//div[@class="flashbags container"]//div[@class="alert-content"]')->text();

            if ($i >= 10) {
                static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => $waitTime]), $errorContent);
            } else {
                static::assertStringContainsString($this->translator->trans('account.orderGuestLoginWrongCredentials'), $errorContent);
            }
        }
    }

    public function testResetAccountOrderRateLimit(): void
    {
        $orderRoute = new OrderRoute(
            $this->getContainer()->get('order.repository'),
            $this->getContainer()->get('promotion.repository'),
            $this->mockResetLimiter([
                RateLimiter::GUEST_LOGIN => 1,
            ]),
        );

        $order = $this->createCustomerWithOrder();

        $controller = new AccountOrderPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(EventDispatcher::class),
            $orderRoute,
            $this->createMock(AccountService::class),
        );

        $controller->load(new Request([
            'deepLinkCode' => $order->getDeepLinkCode(),
            'email' => 'orderTest@example.com',
            'zipcode' => '12345',
        ]), $this->salesChannelContext);
    }

    public function testAuthControllerGuestLoginShowsRateLimit(): void
    {
        $controller = new AuthController(
            $this->getContainer()->get(AccountLoginPageLoader::class),
            $this->getContainer()->get('customer_recovery.repository'),
            $this->createMock(AbstractSendPasswordRecoveryMailRoute::class),
            $this->createMock(AbstractResetPasswordRoute::class),
            $this->createMock(LoginRoute::class),
            $this->createMock(AbstractLogoutRoute::class),
            $this->getContainer()->get(CartService::class)
        );
        $controller->setContainer($this->getContainer());
        $controller->setTwig($this->getContainer()->get('twig'));

        $request = $this->createRequest('frontend.account.guest.login.page', [
            'redirectTo' => 'frontend.account.order.single.page',
            'redirectParameters' => ['deepLinkCode' => 'example'],
            'loginError' => false,
            'waitTime' => 5,
        ]);

        $this->getContainer()->get('request_stack')->push($request);

        /** @var StorefrontResponse $response */
        $response = $controller->guestLoginPage($request, $this->salesChannelContext);

        $contentReturn = $response->getContent();
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="flashbags container"]//div[@class="alert-content"]')->text();

        static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => 5]), $errorContent);
    }

    public function testAuthControllerLoginShowsRateLimit(): void
    {
        $loginRoute = $this->createMock(LoginRoute::class);
        $loginRoute->method('login')->willThrowException(new CustomerAuthThrottledException(5));

        $controller = new AuthController(
            $this->getContainer()->get(AccountLoginPageLoader::class),
            $this->getContainer()->get('customer_recovery.repository'),
            $this->createMock(AbstractSendPasswordRecoveryMailRoute::class),
            $this->createMock(AbstractResetPasswordRoute::class),
            $loginRoute,
            $this->createMock(AbstractLogoutRoute::class),
            $this->getContainer()->get(CartService::class)
        );
        $controller->setContainer($this->getContainer());

        $request = $this->createRequest('frontend.account.login');

        $this->getContainer()->get('request_stack')->push($request);

        /** @var StorefrontResponse $response */
        $response = $controller->login($request, new RequestDataBag([
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]), $this->salesChannelContext);

        $data = $response->getData();

        static::assertTrue($data['loginError']);
        static::assertEquals(5, $data['waitTime']);

        $contentReturn = $response->getContent();
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//form[@class="login-form"]//div[@class="alert-content"]')->text();

        static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => 5]), $errorContent);
    }

    public function testGenerateAccountRecoveryRateLimit(): void
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $request->attributes->add([
            '_route' => 'frontend.account.recover.request',
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $this->ids->get('sales-channel'),
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->salesChannelContext,
            RequestTransformer::STOREFRONT_URL => 'http://localhost',
        ]);

        $this->getContainer()->get('request_stack')->push($request);

        for ($i = 0; $i <= 3; ++$i) {
            $this->browser->request(
                'POST',
                '/account/recover',
                $this->tokenize('frontend.account.recover.request', [
                    'email' => [
                        'email' => 'test@example.com',
                    ],
                ])
            );

            $flashBag = $this->getContainer()->get('session')->getFlashBag();

            if ($i >= 3) {
                static::assertNotEmpty($flash = $flashBag->get('info'));
                static::assertEquals($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 30]), $flash[0]);
            } else {
                static::assertNotEmpty($flash = $flashBag->get('success'));
                static::assertEquals($this->translator->trans('account.recoveryMailSend'), $flash[0]);
            }
        }
    }

    public function testFormControllerRateLimit(): void
    {
        $contactFormRoute = $this->createMock(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException(new RateLimitExceededException(time() + 5));

        $controller = new FormController(
            $contactFormRoute,
            $this->getContainer()->get(NewsletterSubscribeRoute::class),
            $this->getContainer()->get(NewsletterUnsubscribeRoute::class),
        );
        $controller->setContainer($this->getContainer());
        $controller->setTwig($this->getContainer()->get('twig'));

        $response = $controller->sendContactForm(new RequestDataBag([
        ]), $this->salesChannelContext);

        $content = \json_decode($response->getContent(), true);

        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertEquals('info', $content[0]['type']);

        $contentReturn = $content[0]['alert'];
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="alert-content"]')->text();

        static::assertStringContainsString($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 5]), $errorContent);
    }

    public function testItThrowsExceptionOnInvalidRoute(): void
    {
        $rateLimiter = new RateLimiter();

        static::expectException(\RuntimeException::class);
        $rateLimiter->reset('test', 'test-key');
    }

    private function createCustomerWithOrder(): ?OrderEntity
    {
        $orderId = Uuid::randomHex();
        $customerId = $this->createCustomer('shopware', 'orderTest@example.com', true);

        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $customerId,
                'salesChannelId' => $this->ids->get('sales-channel'),
            ],
        ], $this->context);

        $orderData = $this->getOrderData($orderId, $this->context);
        $orderData[0]['orderCustomer']['customer'] = ['id' => $customerId];
        $orderData[0]['orderCustomer']['email'] = 'orderTest@example.com';
        $orderData[0]['orderCustomer']['addresses'][0]['zipcode'] = '12345';
        $orderData[0]['addresses'][0]['zipcode'] = '12345';
        $orderData[0]['salesChannelId'] = $this->ids->get('sales-channel');

        $orderRepsitory = $this->getContainer()->get('order.repository');
        $orderRepsitory->create($orderData, $this->context);

        $order = $orderRepsitory->search(new Criteria([$orderId]), $this->context)->first();

        static::assertNotNull($order);

        return $order;
    }

    private function createRequest(string $route, array $params = []): Request
    {
        $request = new Request();
        $request->query->add($params);
        $request->setSession($this->getContainer()->get('session'));
        $request->attributes->add([
            '_route' => $route,
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $this->ids->get('sales-channel'),
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->salesChannelContext,
            RequestTransformer::STOREFRONT_URL => 'http://localhost',
        ]);

        return $request;
    }

    private function mockResetLimiter(array $factories): RateLimiter
    {
        $rateLimiter = new RateLimiter();

        foreach ($factories as $factory => $expects) {
            $limiter = $this->createMock(LimiterInterface::class);
            $limiter->method('consume')->willReturn(new RateLimit(1, new \DateTimeImmutable(), true, 1));
            $limiter->expects(static::exactly($expects))->method('reset');

            $limiterFactory = $this->createMock(RateLimiterFactory::class);
            $limiterFactory->method('create')->willReturn($limiter);

            $rateLimiter->registerLimiterFactory($factory, $limiterFactory);
        }

        return $rateLimiter;
    }

    private function clearCache(): void
    {
        $this->getContainer()->get('cache.rate_limiter')->clear();
    }

    private function queryFromString(string $url, string $param): string
    {
        $rawParams = parse_url($url, \PHP_URL_QUERY);

        parse_str($rawParams, $params);

        return $params[$param];
    }
}
