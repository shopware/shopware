<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Listener;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderStateChangeEventListenerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTriggerTransactionEvents(): void
    {
        $ids = new TestDataCollection();

        $this->createCustomer($ids);
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

        $this->createCustomer($ids);
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

        $this->createCustomer($ids);
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
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => [
                'id' => $ids->get('order_customer'),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
                'customerId' => $ids->get('customer'),
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

    private function createCustomer(TestDataCollection $ids): string
    {
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $ids->get('customer'),
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $ids->get('customer'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $ids->get('customer');
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
        static::assertIsString($id);

        return $id;
    }

    private function getStateId(string $state, string $machine): ?string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('
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

/**
 * @internal
 */
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
