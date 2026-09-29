<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerMetaFieldSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use OrderFixture;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private Context $context;

    private StateMachineRegistry $stateMachineRegistry;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->context = Context::createDefaultContext();
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
    }

    public function testCompletingAndReopeningOrderUpdatesCustomerMetadata(): void
    {
        [$orderId, $customerId] = $this->createOrder();

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        $customer = $this->fetchCustomer($customerId);
        static::assertSame(1, $customer->getOrderCount());
        static::assertSame(10, (int) $customer->getOrderTotalAmount());
        static::assertNotNull($customer->getLastOrderDate());

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_REOPEN);

        $customer = $this->fetchCustomer($customerId);
        static::assertSame(0, $customer->getOrderCount());
        static::assertSame(0, (int) $customer->getOrderTotalAmount());
        static::assertNull($customer->getLastOrderDate());
    }

    public function testDeletingCompletedOrderUpdatesCustomerMetadata(): void
    {
        [$orderId, $customerId] = $this->createCompletedOrder();

        $customer = $this->fetchCustomer($customerId);
        static::assertSame(1, $customer->getOrderCount());
        static::assertSame(10, (int) $customer->getOrderTotalAmount());
        static::assertNotNull($customer->getLastOrderDate());

        $this->orderRepository->delete([['id' => $orderId]], $this->context);

        $customer = $this->fetchCustomer($customerId);
        static::assertSame(0, $customer->getOrderCount());
        static::assertSame(0, (int) $customer->getOrderTotalAmount());
        static::assertNull($customer->getLastOrderDate());
    }

    public function testDeletingOrdersOfSeveralCustomersAtOnceResetsMetadataOfCustomersWithoutRemainingCompletedOrders(): void
    {
        [$onlyOrderOfFirstCustomer, $firstCustomerId] = $this->createCompletedOrder();
        [$firstOrderOfSecondCustomer, $secondCustomerId] = $this->createCompletedOrder();
        $this->createCompletedOrder($secondCustomerId);

        static::assertSame(1, $this->fetchCustomer($firstCustomerId)->getOrderCount());
        static::assertSame(2, $this->fetchCustomer($secondCustomerId)->getOrderCount());

        // one write deletes the only order of the first customer and one of the two orders of the second customer
        $this->orderRepository->delete([
            ['id' => $onlyOrderOfFirstCustomer],
            ['id' => $firstOrderOfSecondCustomer],
        ], $this->context);

        $firstCustomer = $this->fetchCustomer($firstCustomerId);
        static::assertSame(0, $firstCustomer->getOrderCount());
        static::assertSame(0, (int) $firstCustomer->getOrderTotalAmount());
        static::assertNull($firstCustomer->getLastOrderDate());

        $secondCustomer = $this->fetchCustomer($secondCustomerId);
        static::assertSame(1, $secondCustomer->getOrderCount());
        static::assertSame(10, (int) $secondCustomer->getOrderTotalAmount());
        static::assertNotNull($secondCustomer->getLastOrderDate());
    }

    /**
     * @return array{string, string}
     */
    private function createOrder(?string $customerId = null): array
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context, $customerId);
        // the fixture uses a fixed deep link code, which has to be unique per order
        $orderData[0]['deepLinkCode'] = Random::getAlphanumericString(32);
        $customerId = $orderData[0]['orderCustomer']['customer']['id'];
        static::assertIsString($customerId);

        $this->orderRepository->create($orderData, $this->context);

        return [$orderId, $customerId];
    }

    /**
     * @return array{string, string}
     */
    private function createCompletedOrder(?string $customerId = null): array
    {
        [$orderId, $customerId] = $this->createOrder($customerId);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_PROCESS);
        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_COMPLETE);

        return [$orderId, $customerId];
    }

    private function fetchCustomer(string $customerId): CustomerEntity
    {
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        return $customer;
    }

    private function transitionOrder(string $orderId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition('order', $orderId, $action, 'stateId'),
            $this->context,
        );
    }
}
