<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\OrderPaidException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class EditOrderPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testEditOrderPageShouldLoad(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        /** @var AccountEditOrderPageLoader $event */
        $event = null;
        $this->catchEvent(AccountEditOrderPageLoadedEvent::class, $event);

        $request->request->set('orderId', $orderId);
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountEditOrderPage::class, $page);
        self::assertPageEvent(AccountEditOrderPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertSame($orderId, $page->getOrder()->getId());
    }

    public function testEditPageNotAvailableOrderIsPaid(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $this->setOrderToPaid($orderId, $context);

        /** @var AccountEditOrderPageLoader $event */
        $event = null;
        $this->catchEvent(AccountEditOrderPageLoader::class, $event);

        $this->expectException(OrderPaidException::class);

        $request->request->set('orderId', $orderId);
        $this->getPageLoader()->load($request, $context);
    }

    protected function getPageLoader(): AccountEditOrderPageLoader
    {
        return $this->getContainer()->get(AccountEditOrderPageLoader::class);
    }

    private function setOrderToPaid(string $orderId, SalesChannelContext $context): void
    {
        $order = $this->getOrder($orderId, $context);

        $stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);

        $stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $order->getTransactions()->last()->getId(),
                StateMachineTransitionActions::ACTION_PAID,
                'stateId'
            ),
            $context->getContext()
        );
    }

    private function getOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');

        /* @var OrderEntity $order */
        return $orderRepository->search($criteria, $context->getContext())->first();
    }
}
