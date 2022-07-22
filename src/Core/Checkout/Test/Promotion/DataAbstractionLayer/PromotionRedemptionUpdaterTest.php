<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionRedemptionUpdaterTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private TestDataCollection $ids;

    private Connection $connection;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->salesChannelContext = $this->createSalesChannelContext();
    }

    public function testPromotionRedemptionUpdaterUpdateViaIndexer(): void
    {
        $this->createPromotionsAndOrder();

        $updater = $this->getContainer()->get(PromotionRedemptionUpdater::class);
        $updater->update([$this->ids->get('voucherA'), $this->ids->get('voucherB')], Context::createDefaultContext());

        $this->assertUpdatedCounts();
    }

    public function testPromotionRedemptionUpdaterUpdateViaOrderPlacedEvent(): void
    {
        $this->createPromotionsAndOrder();

        $criteria = new Criteria([$this->ids->get('order')]);
        $criteria->addAssociation('lineItems');
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->salesChannelContext->getContext())->first();
        static::assertNotNull($order);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new CheckoutOrderPlacedEvent(
            $this->salesChannelContext->getContext(),
            $order,
            $this->salesChannelContext->getSalesChannelId()
        ));

        $this->assertUpdatedCounts();
    }

    private function createPromotionsAndOrder(): void
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $this->getContainer()->get('promotion.repository');

        $voucherA = $this->ids->create('voucherA');
        $voucherB = $this->ids->create('voucherB');

        $this->createPromotion($voucherA, $voucherA, $promotionRepository, $this->salesChannelContext);
        $this->createPromotion($voucherB, $voucherB, $promotionRepository, $this->salesChannelContext);

        $this->ids->set('customer', $this->createCustomer('shopware', 'johndoe@example.com'));
        $this->createOrder($this->ids->get('customer'));

        $lineItems = $this->connection->fetchAll('SELECT id FROM order_line_item;');

        static::assertCount(3, $lineItems);
    }

    private function assertUpdatedCounts(): void
    {
        $promotions = $this->connection->fetchAll('SELECT * FROM promotion;');

        static::assertCount(2, $promotions);

        $actualVoucherA = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherA') ? $promotions[0] : $promotions[1];
        static::assertNotEmpty($actualVoucherA);
        static::assertEquals('1', $actualVoucherA['order_count']);
        $customerCount = json_decode($actualVoucherA['orders_per_customer_count'], true);
        static::assertEquals(1, $customerCount[$this->ids->get('customer')]);

        $actualVoucherB = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherB') ? $promotions[0] : $promotions[1];
        static::assertNotEmpty($actualVoucherB);
        // VoucherB is used twice, it's mean group by works
        static::assertEquals('2', $actualVoucherB['order_count']);
        $customerCount = json_decode($actualVoucherB['orders_per_customer_count'], true);
        static::assertEquals(2, $customerCount[$this->ids->get('customer')]);
    }

    private function createSalesChannelContext(array $options = []): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, TestDefaults::SALES_CHANNEL, $options);
    }

    private function createOrder(string $customerId): void
    {
        $this->getContainer()->get('order.repository')->create(
            [[
                'id' => $this->ids->create('order'),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderCustomer' => [
                    'customerId' => $customerId,
                    'email' => 'test@example.com',
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'stateId' => $this->fetchFirstIdFromTable('state_machine'),
                'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'billingAddressId' => Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => Uuid::randomHex(),
                        'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->fetchFirstIdFromTable('country'),
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $this->ids->get('VoucherA'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('VoucherA'),
                        'identifier' => $this->ids->get('VoucherA'),
                        'quantity' => 1,
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherA'),
                            'code' => $this->ids->get('VoucherA'),
                        ],
                        'promotionId' => $this->ids->get('voucherA'),
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $this->ids->get('VoucherC'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('VoucherC'),
                        'identifier' => $this->ids->get('VoucherC'),
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherB'),
                            'code' => $this->ids->get('VoucherC'),
                        ],
                        'promotionId' => $this->ids->get('voucherB'),
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $this->ids->get('VoucherB'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('VoucherB'),
                        'identifier' => $this->ids->get('VoucherB'),
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherB'),
                            'code' => $this->ids->get('VoucherB'),
                        ],
                        'promotionId' => $this->ids->get('voucherB'),
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        $connection = $this->getContainer()->get(Connection::class);

        return Uuid::fromBytesToHex((string) $connection->fetchColumn("SELECT id FROM {$table} LIMIT 1"));
    }
}
