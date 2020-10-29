<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Listener;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderStateChangeEventListenerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTriggerTransactionEvents(): void
    {
        $ids = new TestDataCollection();

        $this->createOrder($ids);

        $this->assertEvent('state_leave.order_transaction.state.open');
        $this->assertEvent('state_enter.order_transaction.state.in_progress');

        $this->getContainer()
            ->get(StateMachineRegistry::class)
            ->transition(
                new Transition(
                    OrderTransactionDefinition::ENTITY_NAME,
                    $ids->get('transaction'),
                    StateMachineTransitionActions::ACTION_DO_PAY,
                    'stateId'
                ),
                Context::createDefaultContext()
            );
    }

    public function testTriggerOrderEvent(): void
    {
        $ids = new TestDataCollection();

        $this->createOrder($ids);
        $this->assertEvent('state_leave.order.state.open');
        $this->assertEvent('state_enter.order.state.in_progress');

        $this->getContainer()
            ->get(StateMachineRegistry::class)
            ->transition(
                new Transition(
                    OrderDefinition::ENTITY_NAME,
                    $ids->get('order'),
                    StateMachineTransitionActions::ACTION_PROCESS,
                    'stateId'
                ),
                Context::createDefaultContext()
            );
    }

    public function testOrderDeliveryEvent(): void
    {
        $ids = new TestDataCollection();

        $this->createOrder($ids);
        $this->assertEvent('state_leave.order_delivery.state.open');
        $this->assertEvent('state_enter.order_delivery.state.shipped');

        $this->getContainer()
            ->get(StateMachineRegistry::class)
            ->transition(
                new Transition(
                    OrderDeliveryDefinition::ENTITY_NAME,
                    $ids->get('delivery'),
                    StateMachineTransitionActions::ACTION_SHIP,
                    'stateId'
                ),
                Context::createDefaultContext()
            );
    }

    public function testRulesForOrder(): void
    {
        $ids = new TestDataCollection();

        $rule = [
            'id' => $ids->create('rule'),
            'name' => 'Demo rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => (new CartAmountRule())->getName(),
                    'value' => [
                        'operator' => Rule::OPERATOR_GTE,
                        'amount' => 200,
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('rule.repository')
            ->create([$rule], Context::createDefaultContext());

        $this->createOrder($ids);

        $validator = new RuleValidator();
        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener('state_enter.order.state.in_progress', $validator);

        $this->getContainer()
            ->get(StateMachineRegistry::class)
            ->transition(
                new Transition(
                    OrderDefinition::ENTITY_NAME,
                    $ids->get('order'),
                    StateMachineTransitionActions::ACTION_PROCESS,
                    'stateId'
                ),
                Context::createDefaultContext()
            );

        static::assertInstanceOf(OrderStateMachineStateChangeEvent::class, $validator->event);
        static::assertContains($ids->get('rule'), $validator->event->getContext()->getRuleIds());
    }

    private function assertEvent(string $event): void
    {
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener($event, $listener);
    }

    private function createOrder(TestDataCollection $ids): void
    {
        $data = [
            'id' => $ids->create('order'),
            'orderNumber' => Uuid::randomHex(),
            'billingAddressId' => $ids->create('billing-address'),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => [
                'id' => $ids->get('customer'),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
            ],
            'addresses' => [
                [
                    'id' => $ids->create('billing-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                [
                    'id' => $ids->create('shipping-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item'),
                    'identifier' => $ids->create('line-item'),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery'),
                    'shippingOrderAddressId' => $ids->create('shipping-address'),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position'),
                            'orderLineItemId' => $ids->create('line-item'),
                            'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
            'transactions' => [
                [
                    'id' => $ids->create('transaction'),
                    'paymentMethodId' => $this->getPrePaymentMethodId(),
                    'stateId' => $this->getStateId('open', 'order_transaction.state'),
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];

        $this->getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function getStateId(string $state, string $machine)
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]);
    }
}

class RuleValidator extends CallableClass
{
    /**
     * @var OrderStateMachineStateChangeEvent|null
     */
    public $event;

    public function __invoke(): void
    {
        $this->event = func_get_arg(0);
    }
}
