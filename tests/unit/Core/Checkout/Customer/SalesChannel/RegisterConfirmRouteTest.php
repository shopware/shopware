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
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(RegisterConfirmRoute::class)]
class RegisterConfirmRouteTest extends TestCase
{
    protected SalesChannelContext&MockObject $context;

    protected EventDispatcherInterface&MockObject $eventDispatcher;

    protected EntityRepository&MockObject $customerRepository;

    protected DataValidator&MockObject $validator;

    protected SalesChannelContextPersister&MockObject $salesChannelContextPersister;

    protected SalesChannelContextServiceInterface&Stub $salesChannelContextService;

    protected RegisterConfirmRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createMock(SalesChannelContext::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->salesChannelContextPersister = $this->createMock(SalesChannelContextPersister::class);

        $newSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $newSalesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        $this->salesChannelContextService = $this->createStub(SalesChannelContextServiceInterface::class);
        $this->salesChannelContextService
            ->method('get')
            ->willReturn($newSalesChannelContext);

        $this->route = new RegisterConfirmRoute(
            $this->customerRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->salesChannelContextPersister,
            $this->salesChannelContextService
        );
    }

    public function testConfirmCustomer(): void
    {
        $customer = $this->mockCustomer();

        $this->customerRepository->expects(static::exactly(2))
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

        $this->customerRepository->expects(static::once())
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

        $this->validator->expects(static::once())
            ->method('validate')
            ->willReturnCallback(function (array $data, DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();
                static::assertArrayHasKey('doubleOptInRegistration', $properties);
                static::assertContainsOnlyInstancesOf(IsTrue::class, $properties['doubleOptInRegistration']);

                static::assertFalse($data['doubleOptInRegistration']);

                throw new ConstraintViolationException(new ConstraintViolationList(), $data);
            });

        static::expectException(ConstraintViolationException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmActivatedCustomer(): void
    {
        $customer = $this->mockCustomer();
        $customer->setActive(true);
        $customer->setDoubleOptInConfirmDate(new \DateTime());

        $this->customerRepository->expects(static::once())
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

        $this->customerRepository->expects(static::once())
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
            'em' => hash('sha1', 'test@test.test'),
        ]);
    }
}
