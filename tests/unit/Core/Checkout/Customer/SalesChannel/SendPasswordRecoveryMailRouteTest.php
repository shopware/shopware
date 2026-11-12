<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\SendPasswordRecoveryMailRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRoute::class)]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    /**
     * @var EntityRepository<CustomerCollection>&Stub
     */
    protected EntityRepository&Stub $customerRepository;

    /**
     * @var EntityRepository<CustomerRecoveryCollection>&Stub
     */
    protected EntityRepository&Stub $customerRecoveryRepository;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    protected DataValidator&Stub $validator;

    protected SystemConfigService&Stub $systemConfigService;

    protected RequestStack&Stub $requestStack;

    protected RateLimiter&Stub $rateLimiter;

    protected SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->customerRepository = static::createStub(EntityRepository::class);
        $this->customerRecoveryRepository = static::createStub(EntityRepository::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->requestStack = static::createStub(RequestStack::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testSendRecoveryMail(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foo');

        $customerRecovery = new CustomerRecoveryEntity();
        $customerRecovery->setId('customer-recovery-id');
        $customerRecovery->setUniqueIdentifier('customer-recovery-id');
        $customerRecovery->setCustomerId($customer->getId());
        $customerRecovery->setHash('super-secret-hash');
        $customerRecovery->setCustomer($customer);

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [[$customer->getId()]]);
        $customerRecoveryRepository = StaticEntityRepository::of(CustomerRecoveryCollection::class, [
            [$customerRecovery->getId()],
            new CustomerRecoveryCollection([$customerRecovery]),
        ]);

        $MailRoute = new SendPasswordRecoveryMailRoute(
            $customerRepository,
            $customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->context->getSalesChannel()->setTranslated(['name' => 'FooBar']);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $data = new RequestDataBag();
        $data->set('email', " \u{00A0}Test@Test.DEV\u{3000}  ");
        $data->set('storefrontUrl', 'https://test.example.dev');

        $MailRoute->sendRecoveryMail($data, $this->context);

        static::assertSame([[['id' => $customerRecovery->getId()]]], $customerRecoveryRepository->deletes);
        static::assertCount(1, $customerRecoveryRepository->creates);
        static::assertCount(1, $customerRecoveryRepository->creates[0]);

        $recoveryData = $customerRecoveryRepository->creates[0][0];
        static::assertSame('foo', $recoveryData['customerId']);
        static::assertSame(32, \strlen($recoveryData['hash']));
    }

    public function testNoCustomerFound(): void
    {
        $mailRoute = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $this->context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public function testRateLimitersAreCalledWithCorrectKeys(): void
    {
        $ip = '10.0.0.1';
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $calls = [];
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });
        $rateLimiter->expects($this->once())
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $route = new SendPasswordRecoveryMailRoute(
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $requestStack,
            $rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->sendRecoveryMail(new RequestDataBag(['email' => " \u{00A0}Test@Test.DEV\u{3000}  "]), $this->context, false);

        static::assertSame([
            [RateLimiter::RESET_PASSWORD_CLIENT, $ip],
            [RateLimiter::RESET_PASSWORD, 'test@test.dev-' . $ip],
            [RateLimiter::RESET_PASSWORD_USER, 'test@test.dev'],
        ], $calls);
    }

    public function testMailSentByAdminSkipsTheClientLimit(): void
    {
        $ip = '10.0.0.1';
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $calls = [];
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });
        $rateLimiter->expects($this->once())
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $route = new SendPasswordRecoveryMailRoute(
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $requestStack,
            $rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $adminContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new AdminSalesChannelApiSource(TestDefaults::SALES_CHANNEL, Context::createDefaultContext())),
        );

        $route->sendRecoveryMail(new RequestDataBag(['email' => 'guest@example.com']), $adminContext, false);

        static::assertSame([
            [RateLimiter::RESET_PASSWORD, 'guest@example.com-' . $ip],
            [RateLimiter::RESET_PASSWORD_USER, 'guest@example.com'],
        ], $calls);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('send-password-recovery-mail-route.send-recovery-mail.pre', function (SendPasswordRecoveryMailRouteExtension $extension) use ($data, $response): void {
            static::assertSame(['data' => $data, 'context' => $this->context, 'validateStorefrontUrl' => true], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SendPasswordRecoveryMailRoute(
            $this->customerRepository,
            $this->customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->sendRecoveryMail($data, $this->context, true));
    }
}
