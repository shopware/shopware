<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerDefaultSalutationSubscriber;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerDefaultSalutationSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    private const NO_SALUTATION = '_no_salutation';

    private const EVENTS = [
        CustomerEvents::CUSTOMER_LOADED_EVENT => CustomerEntity::class,
        CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => CustomerAddressEntity::class,
        OrderEvents::ORDER_ADDRESS_LOADED_EVENT => OrderAddressEntity::class,
        CustomerEvents::CUSTOMER_LOADED_EVENT . self::NO_SALUTATION => CustomerEntity::class,
        CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT . self::NO_SALUTATION => CustomerAddressEntity::class,
        OrderEvents::ORDER_ADDRESS_LOADED_EVENT . self::NO_SALUTATION => OrderAddressEntity::class,
    ];

    private CustomerDefaultSalutationSubscriber $subscriber;

    public function setUp(): void
    {
        $this->subscriber = new CustomerDefaultSalutationSubscriber(
            $this->getContainer()->get('salutation.repository')
        );
    }

    /**
     * @dataProvider eventProvider
     */
    public function testLoadedAddsDefaultSalutation(EntityLoadedEvent $event): void
    {
        static::assertNotEmpty($event->getEntities());
        static::assertContainsOnlyInstancesOf(Entity::class, $event->getEntities());

        // Mocked methods on the provided entities contain further assertions
        $this->subscriber->loaded($event);
    }

    public function eventProvider(): \Generator
    {
        foreach (self::EVENTS as $eventType => $entityType) {
            $event = static::createMock(EntityLoadedEvent::class);
            $entity = static::createMock($entityType);

            if (str_ends_with($eventType, self::NO_SALUTATION)) {
                $entity->method('getSalutation')
                    ->willReturn(null);
                $entity->method('getSalutationId')
                    ->willReturn(null);

                $entity->expects(static::once())
                    ->method('setSalutation')
                    ->with(static::callback(static function (SalutationEntity $salutationEntity): bool {
                        return $salutationEntity->getId() === Defaults::SALUTATION;
                    }));
                $entity->expects(static::once())
                    ->method('setSalutationId')
                    ->with(static::equalTo(Defaults::SALUTATION));
            } else {
                $entity->method('getSalutation')
                    ->willReturn(static::createStub(SalutationEntity::class));
                $entity->method('getSalutationId')
                    ->willReturn(Defaults::SALUTATION);

                $entity->expects(static::never())
                    ->method('setSalutation');
                $entity->expects(static::never())
                    ->method('setSalutationId');
            }

            $event->method('getContext')
                ->willReturn(Context::createDefaultContext());

            $event->method('getEntities')
                ->willReturn([$entity]);

            yield $eventType => [$event];
        }
    }
}
