<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\OrderPaidException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function testEditOrderPageCorrectPayment(): void
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

        static::assertCount(1, $page->getPaymentMethods());

        // set Payment active to false and assert it will not be loaded
        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $paymentMethodRepository->search($criteria, $context->getContext())->first();

        $paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'active' => false],
            ],
            $context->getContext()
        );

        $request->request->set('orderId', $orderId);
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountEditOrderPage::class, $page);
        self::assertPageEvent(AccountEditOrderPageLoadedEvent::class, $event, $context, $request, $page);

        static::assertCount(0, $page->getPaymentMethods());
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

    public function testShouldOnlyShowAvailablePaymentsForOrder(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        // Get customer from USA rule
        $ruleCriteria = new Criteria();
        $ruleCriteria->addFilter(new EqualsFilter('name', 'Customers from USA'));

        $ruleRepository = $this->getContainer()->get('rule.repository');

        $ruleId = $ruleRepository->search($ruleCriteria, $context->getContext())->first()->getId();

        $this->createCustomPaymentWithRule($context, $ruleId);

        // Fake context rules for USA customers
        $context->setRuleIds(array_merge($context->getRuleIds(), [$ruleId]));

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(AccountEditOrderPage::class, $page);
        static::assertSame($orderId, $page->getOrder()->getId());
        static::assertCount(1, $page->getPaymentMethods());
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

        return $orderRepository->search($criteria, $context->getContext())->first();
    }

    private function createCustomPaymentWithRule(SalesChannelContext $context, string $ruleId): string
    {
        $paymentId = Uuid::randomHex();

        $this->getContainer()->get('payment_method.repository')->create([
            [
                'id' => $paymentId,
                'name' => 'Test Payment with Rule',
                'description' => 'Payment rule test',
                'active' => true,
                'afterOrderEnabled' => true,
                'availabilityRuleId' => $ruleId,
                'salesChannels' => [
                    [
                        'id' => $context->getSalesChannelId(),
                    ],
                ],
            ],
        ], $context->getContext());

        return $paymentId;
    }
}
