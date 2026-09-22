<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ChangeEmailRoute::class)]
class ChangeEmailRouteTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $route = new ChangeEmailRoute(
            StaticEntityRepository::of(CustomerCollection::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(CustomerRecoveryCollection::class),
        );

        $this->expectExceptionObject(new DecorationPatternException(ChangeEmailRoute::class));
        $route->getDecorated();
    }

    public function testChangesEmailAndDeletesRecoveryEntities(): void
    {
        $customerId = 'customer-id';
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $recoveryRepository = StaticEntityRepository::of(CustomerRecoveryCollection::class, [['recovery-id']]);
        $route = new ChangeEmailRoute(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $recoveryRepository,
        );

        $result = $route->change(new RequestDataBag([
            'email' => 'new@example.com',
            'emailConfirmation' => 'new@example.com',
            'password' => 'password',
        ]), $context, $customer);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame([[['id' => $customerId, 'email' => 'new@example.com']]], $customerRepository->updates);
        static::assertSame([[['id' => 'recovery-id']]], $recoveryRepository->deletes);
    }

    public function testRejectsDifferentEmailConfirmationBeforeUpdatingCustomer(): void
    {
        $customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $context = Generator::generateSalesChannelContext();
        $route = new ChangeEmailRoute(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(CustomerRecoveryCollection::class, [[]]),
        );
        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $data = [
            'email' => 'new@example.com',
            'emailConfirmation' => 'different@example.com',
            'password' => 'password',
        ];
        $this->expectExceptionObject(new ConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value should be equal to different@example.com.',
                    'This value should be equal to {{ compared_value }}.',
                    [],
                    '',
                    'email',
                    'new@example.com',
                ),
            ]),
            $data,
        ));

        $route->change(new RequestDataBag($data), $context, $customer);
    }

    public function testAllowsCustomValidationDefinitionWithoutEmailEqualityConstraint(): void
    {
        $customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (BuildValidationEvent $event): object {
                $event->getDefinition()->set('email', new NotBlank());

                return $event;
            });

        $route = new ChangeEmailRoute(
            $customerRepository,
            $eventDispatcher,
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(CustomerRecoveryCollection::class, [[]]),
        );
        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $route->change(new RequestDataBag([
            'email' => 'new@example.com',
            'emailConfirmation' => 'different@example.com',
            'password' => 'password',
        ]), Generator::generateSalesChannelContext(), $customer);

        static::assertCount(1, $customerRepository->updates);
    }
}
