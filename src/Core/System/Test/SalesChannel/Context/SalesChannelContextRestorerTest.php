<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Test\Customer\CustomerBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestorerOrderCriteriaEvent;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('sales-channel')]
class SalesChannelContextRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SalesChannelContextRestorer $contextRestorer;

    /**
     * @var array<string, Event>
     */
    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        /** @var AbstractSalesChannelContextFactory $contextFactory */
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $cartRuleLoader = $this->getContainer()->get(CartRuleLoader::class);

        $this->contextRestorer = new SalesChannelContextRestorer(
            $contextFactory,
            $cartRuleLoader,
            $this->getContainer()->get(OrderConverter::class),
            $this->getContainer()->get('order.repository'),
            $this->connection,
            $this->eventDispatcher
        );
    }

    public function testRestoreByOrder(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        $this->getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByOrder($ids->create('order'), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByCustomer(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        $this->getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByCustomerPassesStates(): void
    {
        $context = Context::createDefaultContext();
        $context->addState('foo');

        $ids = new TestDataCollection();
        $this->createOrder($ids);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue($saleChanelContext->getContext()->hasState('foo'));
    }

    public function testOrderCriteriaEventIsFired(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);

        $this->eventDispatcher->addListener(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->callbackFn);
        $this->contextRestorer->restoreByOrder($ids->create('order'), $context);

        static::assertArrayHasKey(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->events);
        $salesChannelContextRestorerCriteriaEvent = $this->events[SalesChannelContextRestorerOrderCriteriaEvent::class];
        static::assertInstanceOf(SalesChannelContextRestorerOrderCriteriaEvent::class, $salesChannelContextRestorerCriteriaEvent);
    }

    private function createOrder(TestDataCollection $ids): void
    {
        $customer = (new CustomerBuilder($ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'))->build();

        $data = [
            'id' => $ids->create('order'),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
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
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => [
                'id' => $ids->get('customer'),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
                'customer' => $customer,
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

    private function getStateId(string $state, string $machine): ?string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne(
                '
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ',
                [
                    'state' => $state,
                    'machine' => $machine,
                ]
            );
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

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();

        $productNumber = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }
}
