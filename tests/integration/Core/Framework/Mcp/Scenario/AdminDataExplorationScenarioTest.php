<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\McpEntityIncludes;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OrderSummaryTool::class)]
#[CoversClass(EntitySearchTool::class)]
#[CoversClass(EntitySchemaTool::class)]
#[CoversClass(McpEntityIncludes::class)]
#[CoversClass(McpToolResponse::class)]
class AdminDataExplorationScenarioTest extends McpScenarioTestCase
{
    public function testUS1OrderSummaryByOrderNumber(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $orderNumber = 'MCP-US1-' . Uuid::randomHex();

        $customer = (new CustomerBuilder($ids, 'US1-cust'))
            ->add('email', 'mcp-us1@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);

        $order = (new OrderBuilder($ids, $orderNumber))
            ->add('orderCustomer', [
                'id' => $ids->get('orderCustomer'),
                'customerId' => $ids->get('US1-cust'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'mcp-us1@example.com',
            ])
            ->addAddress('billing-address')
            ->addTransaction('transaction')
            ->add('lineItems', [[
                'id' => $ids->create('line-item'),
                'identifier' => $ids->get('line-item'),
                'label' => 'Test Product',
                'quantity' => 2,
                'type' => 'custom',
                'price' => ['unitPrice' => 10, 'totalPrice' => 20, 'quantity' => 2, 'calculatedTaxes' => [], 'taxRules' => []],
                'position' => 1,
            ]])
            ->add('deliveries', [[
                'id' => $ids->create('delivery'),
                'stateId' => $this->getStateMachineState(
                    \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates::STATE_MACHINE,
                    \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates::STATE_OPEN,
                ),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingCosts' => ['unitPrice' => 0, 'totalPrice' => 0, 'quantity' => 1, 'calculatedTaxes' => [], 'taxRules' => []],
                'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingOrderAddress' => [
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'city' => 'Berlin',
                    'street' => 'Teststr. 1',
                    'zipcode' => '10115',
                    'country' => ['id' => $ids->create('country'), 'name' => 'Germany'],
                ],
            ]])
            ->build();

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        $output = ($this->orderSummaryTool)(orderNumber: $orderNumber);
        $data = $this->decodeToolOutput($output);

        static::assertSame($orderNumber, $data['data']['orderNumber']);
        static::assertNotNull($data['data']['status']);
        static::assertIsFloat($data['data']['amountTotal']);
        static::assertSame('mcp-us1@example.com', $data['data']['customer']['email']);
        static::assertNotEmpty($data['data']['lineItems']);
        static::assertSame('Test Product', $data['data']['lineItems'][0]['label']);
        static::assertNotEmpty($data['data']['transactions']);
        static::assertNotEmpty($data['data']['deliveries']);
    }

    public function testUS2LowStockProducts(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $products = [
            (new ProductBuilder($ids, 'p-high'))->price(100)->stock(10)->build(),
            (new ProductBuilder($ids, 'p-low1'))->price(50)->stock(3)->build(),
            (new ProductBuilder($ids, 'p-low2'))->price(25)->stock(1)->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, $context);

        $output = ($this->entitySearchTool)('product', json_encode([
            'filter' => [
                ['type' => 'range', 'field' => 'stock', 'parameters' => ['lt' => 5]],
                ['type' => 'multi', 'operator' => 'OR', 'queries' => [
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low1')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low2')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-high')],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = $this->decodeToolOutput($output);

        static::assertSame(2, $data['_meta']['total']);
        foreach ($data['data'] as $product) {
            static::assertLessThan(5, $product['stock']);
        }
    }

    public function testSearchWith25ProductsAndAssociationsStaysUnder100KB(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $products = [];
        for ($i = 0; $i < 25; ++$i) {
            $products[] = (new ProductBuilder($ids, "prod-$i"))
                ->price(100)
                ->stock(10)
                ->manufacturer('manufacturer')
                ->property('red', 'color')
                ->property('XL', 'size')
                ->build();
        }

        static::getContainer()->get('product.repository')->create($products, $context);

        $criteria = json_encode([
            'ids' => array_map(fn (int $i) => $ids->get("prod-$i"), range(0, 24)),
            'associations' => [
                'properties' => ['associations' => ['group' => new \stdClass()]],
                'manufacturer' => new \stdClass(),
            ],
        ], \JSON_THROW_ON_ERROR);

        $output = ($this->entitySearchTool)('product', $criteria);
        $data = $this->decodeToolOutput($output);

        static::assertCount(25, $data['data'], 'All 25 products must be returned without truncation');
        static::assertSame(25, $data['_meta']['total']);
        static::assertArrayNotHasKey('truncated', $data['_meta']);
        static::assertLessThan(100_000, \strlen($output), 'Response must stay under 100KB');
    }

    public function testUS3CustomerEntitySchema(): void
    {
        $output = ($this->entitySchemaTool)('customer');
        $data = $this->decodeToolOutput($output);

        $fieldNames = array_column($data['data']['fields'], 'name');
        static::assertContains('email', $fieldNames);
        static::assertContains('firstName', $fieldNames);
        static::assertContains('lastName', $fieldNames);
        static::assertContains('customerNumber', $fieldNames);

        $assocNames = array_column($data['data']['associations'], 'name');
        static::assertContains('orderCustomers', $assocNames);
        static::assertContains('addresses', $assocNames);
        static::assertContains('group', $assocNames);
    }
}
