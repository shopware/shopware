<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Framework\Api\Controller\AuthController as AdminAuthController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @group slow
 */
class RateLimiterTest extends TestCase
{
    use RateLimiterTestTrait;
    use CustomerTestTrait;
    use OrderFixture;

    private Context $context;

    private TestDataCollection $ids;

    private KernelBrowser $browser;

    private ?AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private SalesChannelContext $salesChannelContext;

    private ?TranslatorInterface $translator;

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

    public function setUp(): void
    {
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
        DisableRateLimiterCompilerPass::enableNoLimit();
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
            ], $this)
        );

        $this->createCustomer('shopware', 'loginTest@example.com');

        $this->getContainer()->get('request_stack')->push(new Request([
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
                static::assertEquals(6, $response['errors'][0]['code']);
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
            ], $this)
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
            ], $this),
        );

        $controller->updateUserPassword(new Request(), $this->context);
    }

    public function testItThrowsExceptionOnInvalidRoute(): void
    {
        $rateLimiter = new RateLimiter();

        static::expectException(\RuntimeException::class);
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
            $this->createMock(LockFactory::class)
        );

        static::assertInstanceOf(NoLimiter::class, $factory->create('example'));
    }
}
