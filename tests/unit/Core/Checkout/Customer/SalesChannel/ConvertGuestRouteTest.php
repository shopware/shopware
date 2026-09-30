<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Extension\ConvertGuestRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ConvertGuestRoute::class)]
class ConvertGuestRouteTest extends TestCase
{
    private ConvertGuestRoute $route;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private DataValidator&Stub $validator;

    private DataValidationFactoryInterface&Stub $passwordValidationFactory;

    private SalesChannelContext $salesChannelContext;

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        $this->customerRepository = new StaticEntityRepository([]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);

        $this->route = $this->buildRoute();

        $this->salesChannelContext = Generator::generateSalesChannelContext();

        $this->customer = new CustomerEntity();
        $this->customer->setId('test-customer-id');
        $this->customer->setEmail('test@example.com');
        $this->customer->setGuest(true);
    }

    public function testConvertGuestSuccess(): void
    {
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $passwordDefinition = new DataValidationDefinition('customer.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                static::anything(),
                static::equalTo('framework.validation.customer.guest.convert')
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => 'new-password',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(salesChannelContext: $this->salesChannelContext)],
                ], $definition->getProperties());

                return true;
            }));

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);
        $route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);

        static::assertSame([[[
            'id' => 'test-customer-id',
            'email' => 'test@example.com',
            'guest' => false,
            'password' => 'new-password',
        ]]], $this->customerRepository->updates);
    }

    public function testConvertGuestFailsForRegisteredCustomer(): void
    {
        $this->customer->setGuest(false);
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $this->expectExceptionObject(CustomerException::registeredCustomerCannotBeConverted('test-customer-id'));

        $this->route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
    }

    public function testConvertGuestFailsWithValidationErrors(): void
    {
        $requestDataBag = new RequestDataBag(['password' => '']);
        $violations = new ConstraintViolationException(
            new ConstraintViolationList(),
            [
                'id' => 'test-customer-id',
                'guest' => false,
                'password' => '',
                'email' => 'test@example.com']
        );

        $passwordDefinition = new DataValidationDefinition('customer.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch');

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => '',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(salesChannelContext: $this->salesChannelContext)],
                ], $definition->getProperties());

                return true;
            }))
            ->willThrowException($violations);

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);

        $this->expectExceptionObject($violations);

        try {
            $route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
        } finally {
            static::assertEmpty($this->customerRepository->updates);
        }
    }

    public function testPublishesExtension(): void
    {
        $requestDataBag = new RequestDataBag();
        $additionalValidationDefinitions = new DataValidationDefinition();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('convert-guest-route.convert-guest.pre', function (ConvertGuestRouteExtension $extension) use ($requestDataBag, $additionalValidationDefinitions, $response): void {
            static::assertSame(['requestDataBag' => $requestDataBag, 'context' => $this->salesChannelContext, 'customer' => $this->customer, 'additionalValidationDefinitions' => $additionalValidationDefinitions], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ConvertGuestRoute(
            $this->customerRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->passwordValidationFactory,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer, $additionalValidationDefinitions));
    }

    private function buildRoute(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidator $validator = null,
        ?DataValidationFactoryInterface $passwordValidationFactory = null
    ): ConvertGuestRoute {
        return new ConvertGuestRoute(
            $this->customerRepository,
            $eventDispatcher ?? $this->eventDispatcher,
            $validator ?? $this->validator,
            $passwordValidationFactory ?? $this->passwordValidationFactory,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
