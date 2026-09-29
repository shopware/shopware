<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegisterConfirmRoute::class)]
class RegisterConfirmRouteTest extends TestCase
{
    protected SalesChannelContext&Stub $context;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    /**
     * @var EntityRepository<CustomerCollection>&MockObject
     */
    protected EntityRepository&MockObject $customerRepository;

    protected DataValidator&Stub $validator;

    protected SalesChannelContextPersister&Stub $salesChannelContextPersister;

    protected SalesChannelContextServiceInterface&Stub $salesChannelContextService;

    protected RegisterConfirmRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = static::createStub(SalesChannelContext::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->salesChannelContextPersister = static::createStub(SalesChannelContextPersister::class);

        $newSalesChannelContext = static::createStub(SalesChannelContext::class);
        $newSalesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        $this->salesChannelContextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->salesChannelContextService
            ->method('get')
            ->willReturn($newSalesChannelContext);

        $this->route = $this->createRoute();
    }

    public function testConfirmCustomer(): void
    {
        $customer = $this->mockCustomer();

        $this->customerRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $confirmResult = $this->route->confirm($this->mockRequestDataBag(), $this->context);

        static::assertTrue($confirmResult->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testConfirmCustomerNotDoubleOptIn(): void
    {
        $customer = $this->mockCustomer();
        $customer->setDoubleOptInRegistration(false);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(static function (array $data, DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();
                static::assertArrayHasKey('doubleOptInRegistration', $properties);
                static::assertContainsOnlyInstancesOf(IsTrue::class, $properties['doubleOptInRegistration']);

                static::assertFalse($data['doubleOptInRegistration']);

                throw new ConstraintViolationException(new ConstraintViolationList(), $data);
            });

        $route = $this->createRoute($validator);

        static::expectException(ConstraintViolationException::class);
        $route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmActivatedCustomer(): void
    {
        $customer = $this->mockCustomer();
        $customer->setActive(true);
        $customer->setDoubleOptInConfirmDate(new \DateTime());

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(CustomerAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmConfirmedCustomer(): void
    {
        $customer = $this->mockCustomer();
        $customer->setDoubleOptInConfirmDate(new \DateTime());

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(CustomerAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    protected function mockCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setActive(false);
        $customer->setEmail('test@test.test');
        $customer->setHash('hash');
        $customer->setGuest(false);
        $customer->setDoubleOptInRegistration(true);
        $customer->setDoubleOptInEmailSentDate(new \DateTime());

        return $customer;
    }

    protected function mockRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag([
            'hash' => 'hash',
            'em' => Hasher::hash('test@test.test', 'sha1'),
        ]);
    }

    private function createRoute(?DataValidator $validator = null): RegisterConfirmRoute
    {
        return new RegisterConfirmRoute(
            $this->customerRepository,
            $this->eventDispatcher,
            $validator ?? $this->validator,
            $this->salesChannelContextPersister,
            $this->salesChannelContextService,
            new NativeClock()
        );
    }
}
