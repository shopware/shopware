<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class ConvertGuestRouteTest extends TestCase
{
    private ConvertGuestRoute $route;

    private EntityRepository $customerRepository;

    private EventDispatcherInterface $eventDispatcher;

    private DataValidator $validator;

    private DataValidationFactoryInterface $passwordValidationFactory;

    private SalesChannelContext $salesChannelContext;

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);

        $this->route = new ConvertGuestRoute(
            $this->customerRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->passwordValidationFactory
        );

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

        $this->passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(function (BuildValidationEvent $event) {
                    return $event->getDefinition() instanceof DataValidationDefinition;
                }),
                static::stringContains('customer.guest.convert')
            );

        $data = [
            'id' => 'test-customer-id',
            'guest' => false,
            'password' => 'new-password',
            'email' => 'test@example.com',
        ];

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($data, static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(['salesChannelContext' => $this->salesChannelContext, 'context' => $this->salesChannelContext->getContext()])],
                ], $definition->getProperties());

                return true;
            }));

        $this->customerRepository->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => 'test-customer-id',
                    'guest' => false,
                    'password' => 'new-password',
                ]],
                $this->salesChannelContext->getContext(),
            );

        $this->route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
    }

    public function testConvertGuestFailsForRegisteredCustomer(): void
    {
        $this->customer->setGuest(false);
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Customer with id "test-customer-id" is not a guest');

        $this->route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
    }

    public function testConvertGuestFailsWithValidationErrors(): void
    {
        $requestDataBag = new RequestDataBag(['password' => '']);

        $passwordDefinition = new DataValidationDefinition('customer.password');
        $passwordDefinition->add('password', new NotBlank());

        $this->passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($passwordDefinition);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch');

        $data = [
            'id' => 'test-customer-id',
            'guest' => false,
            'password' => '',
            'email' => 'test@example.com',
        ];

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($data, static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('customer.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new CustomerEmailUnique(['salesChannelContext' => $this->salesChannelContext, 'context' => $this->salesChannelContext->getContext()])],
                ], $definition->getProperties());

                return true;
            }))
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), $data));

        $this->customerRepository->expects($this->never())
            ->method('update');

        $this->expectException(ConstraintViolationException::class);

        $this->route->convertGuest($requestDataBag, $this->salesChannelContext, $this->customer);
    }
}
