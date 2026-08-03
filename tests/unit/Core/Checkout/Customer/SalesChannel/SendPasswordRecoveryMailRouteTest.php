<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
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

        $customerCollection = new CustomerCollection([$customer]);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    $customerCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $customerRecoveryRepository = $this->createMock(EntityRepository::class);
        $customerRecoveryRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static function (array $recoveryData): bool {
                    static::assertCount(1, $recoveryData);

                    $updateData = $recoveryData[0];

                    static::assertArrayHasKey('customerId', $updateData);
                    static::assertArrayHasKey('hash', $updateData);

                    static::assertSame('foo', $updateData['customerId']);
                    static::assertSame(32, \strlen($updateData['hash']));

                    return true;
                }),
                $this->context->getContext()
            );

        $customerRecovery = new CustomerRecoveryEntity();
        $customerRecovery->setId('customer-recovery-id');
        $customerRecovery->setUniqueIdentifier('customer-recovery-id');
        $customerRecovery->setCustomerId($customer->getId());
        $customerRecovery->setHash('super-secret-hash');
        $customerRecovery->setCustomer($customer);

        $customerRecoveryCollection = new CustomerRecoveryCollection([$customerRecovery]);

        $customerRecoveryRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer_recovery',
                    1,
                    $customerRecoveryCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $MailRoute = new SendPasswordRecoveryMailRoute(
            $customerRepository,
            $customerRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter
        );

        $this->context->getSalesChannel()->setTranslated(['name' => 'FooBar']);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $data = new RequestDataBag();
        $data->set('email', 'test@test.dev');
        $data->set('storefrontUrl', 'https://test.example.dev');

        $MailRoute->sendRecoveryMail($data, $this->context);
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
            $this->rateLimiter
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $this->context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }
}
