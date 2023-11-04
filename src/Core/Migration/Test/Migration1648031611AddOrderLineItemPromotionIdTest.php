<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1648031611AddOrderLineItemPromotionId;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1648031611AddOrderLineItemPromotionIdTest extends TestCase
{
    use KernelTestBehaviour;
    use OrderFixture;
    use PromotionTestFixtureBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testMigrationColumn(): void
    {
        $this->removeColumn();
        static::assertFalse($this->hasColumn('order_line_item', 'promotion_id'));

        $migration = new Migration1648031611AddOrderLineItemPromotionId();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasColumn('order_line_item', 'promotion_id'));
    }

    /**
     * @dataProvider dataProviderPromotion
     */
    public function testMigrationMigratesPromotionId(bool $promotionExists): void
    {
        $context = Context::createDefaultContext();

        $this->buildPromotionLineItem($context, $promotionExists);

        /** @var EntityRepository $orderLineItemRepository */
        $orderLineItemRepository = $this->getContainer()->get('order_line_item.repository');

        $lineItem = $orderLineItemRepository->search(new Criteria([$this->ids->get('line-item')]), $context)->first();
        static::assertNotNull($lineItem);
        static::assertNull($lineItem->getPromotionId());

        $this->removeColumn();
        $migration = new Migration1648031611AddOrderLineItemPromotionId();
        $migration->update($this->connection);

        $lineItem = $orderLineItemRepository->search(new Criteria([$this->ids->get('line-item')]), $context)->first();
        static::assertNotNull($lineItem);
        if ($promotionExists) {
            static::assertSame($this->ids->get('promotion'), $lineItem->getPromotionId());
        } else {
            static::assertNull($lineItem->getPromotionId());
        }

        // this is needed because we are not in a transaction due to the migration
        $this->removeEntities($context);
    }

    /**
     * @return iterable<array{0: bool}>
     */
    public static function dataProviderPromotion(): iterable
    {
        return [
            [true],
            [false],
        ];
    }

    public function removeEntities(Context $context): void
    {
        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $orderRepository->delete([['id' => $this->ids->get('order')]], $context);

        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $this->getContainer()->get('promotion.repository');
        $promotionRepository->delete([['id' => $this->ids->get('promotion')]], $context);
    }

    private function removeColumn(): void
    {
        if ($this->hasColumn('order_line_item', 'promotion_id')) {
            $this->connection->executeStatement('ALTER TABLE `order_line_item` DROP FOREIGN KEY `fk.order_line_item.promotion_id`');
            $this->connection->executeStatement('ALTER TABLE `order_line_item` DROP COLUMN `promotion_id`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \in_array($columnName, array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }

    private function buildPromotionLineItem(Context $context, bool $promotionExists): void
    {
        if ($promotionExists) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId(TestDefaults::SALES_CHANNEL);

            $this->createTestFixtureFixedDiscountPromotion(
                $this->ids->get('promotion'),
                70,
                PromotionDiscountEntity::SCOPE_CART,
                $this->ids->get('promotion-code'),
                $this->getContainer(),
                Generator::createSalesChannelContext($context, null, $salesChannel)
            );
        }

        $orderData = $this->getOrderData($this->ids->get('order'), $context);
        unset($orderData[0]['orderCustomer']);
        $orderData[0]['lineItems'][] = [
            'id' => $this->ids->get('line-item'),
            'identifier' => '97838d40733d4ae3ad11f4b09f054176',
            'referencedId' => $this->ids->get('promotion-code'),
            'quantity' => 1,
            'label' => 'Test',
            'payload' => [
                'promotionId' => $this->ids->get('promotion'),
                'discountId' => '97838d40733d4ae3ad11f4b09f054176',
                'discountType' => 'percentage',
                'code' => $this->ids->get('promotion-code'),
                'value' => '5',
                'promotionCodeType' => 'fixed',
                'maxValue' => '55',
                'discountScope' => 'cart',
                'preventCombination' => false,
                'exclusions' => [],
                'groupId' => '',
                'setGroups' => [],
            ],
            'good' => false,
            'removable' => true,
            'stackable' => false,
            'price' => new CalculatedPrice(-1.1, -1.1, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'priceDefinition' => new PercentagePriceDefinition(-5),
            'unitPrice' => -1.1,
            'totalPrice' => -1.1,
            'position' => 2,
            'description' => 'Test',
            'type' => PromotionProcessor::LINE_ITEM_TYPE,
        ];

        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        $orderRepository->create($orderData, $context);
    }
}
