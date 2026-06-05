<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\RateLimiter;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Controller\AuthController as AdminAuthController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\Framework\Test\RateLimiter\RateLimiterTestTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopware\Core\Test\Integration\Traits\OrderFixture;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @internal
 */
#[Group('slow')]
class RateLimiterTest extends TestCase
{
    use CustomerTestTrait;
    use OrderFixture;
    use RateLimiterTestTrait;

    private const TEST_THROTTLE_LIMIT = 1;

    private Context $context;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $this->salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class)->getDecorated();

        $this->clearCache();
        $this->overrideRateLimiters();
    }

    protected function tearDown(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
    }

    public function testRateLimitLoginRoute(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'wrongPassword';
        $this->createCustomer($email);

        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => $password,
                    ]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
                static::assertSame('Unauthorized', $response['errors'][0]['title']);
            }
        }
    }

    public function testRateLimitLoginRouteByUserWithRotatingIps(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => 'wrongPassword',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.' . $i]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
            }
        }
    }

    public function testRateLimitLoginRouteByClientWithRotatingEmails(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $email = 'user' . $i . '@example.com';
            $this->createCustomer($email);

            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => 'wrongPassword',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.1']
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
            }
        }
    }

    public function testResetRateLimitLoginRoute(): void
    {
        $route = new LoginRoute(
            static::getContainer()->get(AccountService::class),
            static::getContainer()->get('request_stack'),
            $this->mockResetLimiter([
                RateLimiter::LOGIN_ROUTE => 1,
            ])
        );

        $this->createCustomer('loginTest@example.com');

        static::getContainer()->get('request_stack')->push(new Request([
            'email' => 'loginTest@example.com',
            'password' => 'shopware',
        ]));

        $route->login(new RequestDataBag([
            'email' => 'loginTest@example.com',
            'password' => 'shopware',
        ]), $this->salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL));
    }

    public function testRateLimitOauth(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
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

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testRateLimitOauthByUserWithRotatingIps(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'admin',
                        'password' => 'bla',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.' . $i]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testRateLimitOauthByClientWithRotatingUsernames(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'user' . $i,
                        'password' => 'bla',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.1']
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testResetRateLimitOauth(): void
    {
        $psrFactory = $this->createMock(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn($this->createMock(ServerRequest::class));
        $psrFactory->method('createResponse')->willReturn($this->createMock(ResponseInterface::class));

        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->method('respondToAccessTokenRequest')->willReturn(new Response());

        $controller = new AdminAuthController(
            $authorizationServer,
            $psrFactory,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
            ]),
        );

        $controller->token(new Request());
    }

    public function testRateLimitContactForm(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
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

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertArrayHasKey('errors', $response, print_r($response, true));
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitUserRecovery(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/_action/user/user-recovery',
                    [
                        'email' => 'test@example.com',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertJson((string) $response, (string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($response);
                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testResetRateLimtitUserRecovery(): void
    {
        $recoveryService = $this->createMock(UserRecoveryService::class);
        $userEntity = new UserEntity();
        $userEntity->setUsername('admin');
        $userEntity->setEmail('test@test.de');
        $recoveryService->method('getUserByHash')->willReturn($userEntity);
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

    public function testItThrowsExceptionOnInvalidRoute(): void
    {
        $rateLimiter = new RateLimiter();

        $this->expectException(\RuntimeException::class);
        $rateLimiter->reset('test', 'test-key');
    }

    public function testIgnoreLimitWhenDisabled(): void
    {
        $config = [
            'enabled' => false,
            'id' => 'test_limit',
            'policy' => 'time_backoff',
            'reset' => '5 minutes',
            'limits' => [
                [
                    'limit' => 3,
                    'interval' => '10 seconds',
                ],
            ],
        ];

        $factory = new RateLimiterFactory(
            $config,
            new CacheStorage(new ArrayAdapter()),
            $this->createMock(SystemConfigService::class),
            new NativeClock(),
            $this->createMock(LockFactory::class),
        );

        static::assertInstanceOf(NoLimiter::class, $factory->create('example'));
    }

    public function testRateLimitNewsletterSubscribeForm(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/subscribe',
                    [
                        'email' => 'test@example.com',
                        'option' => 'subscribe',
                        'storefrontUrl' => 'http://localhost',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertJson((string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitNewsletterUnsubscribeForm(): void
    {
        $emailList = [
            'testOne@example.com',
            'testTwo@example.com',
        ];

        $this->createNewsletterRecipient($emailList);

        foreach ($emailList as $email) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/unsubscribe',
                    [
                        'email' => $email,
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($email === 'testTwo@example.com') {
                static::assertJson((string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    /**
     * @param List<string> $emailList
     */
    private function createNewsletterRecipient(array $emailList): void
    {
        $newsletterRecipients = [];
        foreach ($emailList as $email) {
            $newsletterRecipients[] = [
                'email' => $email,
                'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
                'hash' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ];
        }

        $this->getContainer()->get('newsletter_recipient.repository')->upsert($newsletterRecipients, $this->context);
    }

    private function overrideRateLimiters(): void
    {
        $limitOneConfig = [
            'enabled' => true,
            'policy' => 'time_backoff',
            'reset' => '1 hour',
            'limits' => [['limit' => 1, 'interval' => '1 hour']],
        ];

        $routes = [
            RateLimiter::LOGIN_ROUTE,
            RateLimiter::LOGIN_USER,
            RateLimiter::LOGIN_CLIENT,
            RateLimiter::OAUTH,
            RateLimiter::OAUTH_USER,
            RateLimiter::OAUTH_CLIENT,
            RateLimiter::CONTACT_FORM,
            RateLimiter::USER_RECOVERY,
            RateLimiter::NEWSLETTER_FORM,
            RateLimiter::NEWSLETTER_UNSUBSCRIBE_FORM,
        ];

        // LoginRoute is injected with RateLimiter::class, AuthController with 'shopware.rate_limiter'
        // These may be separate instances in the compiled container, so override both
        foreach ([RateLimiter::class, 'shopware.rate_limiter'] as $serviceId) {
            $rateLimiter = static::getContainer()->get($serviceId);
            \assert($rateLimiter instanceof RateLimiter);
            foreach ($routes as $name) {
                $rateLimiter->registerLimiterFactory($name, new RateLimiterFactory(
                    $limitOneConfig + ['id' => $name],
                    new CacheStorage(new ArrayAdapter()),
                    static::createStub(SystemConfigService::class),
                    new NativeClock(),
                ));
            }
        }
    }
}
