<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\BestsellerReportTool;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RevenueReportTool::class)]
#[CoversClass(BestsellerReportTool::class)]
class AnalyticsScenarioTest extends McpScenarioTestCase
{
    public function testUS16RevenueReport(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $amounts = [100.0, 200.0, 150.0];
        $expectedTotal = array_sum($amounts);

        foreach ($amounts as $i => $amount) {
            $orderNumber = 'MCP-US16-' . $i . '-' . Uuid::randomHex();
            $email = 'mcp-us16-' . $i . '-' . Uuid::randomHex() . '@example.com';

            $customer = (new CustomerBuilder($ids, 'cust-' . $i))
                ->add('email', $email)
                ->add('password', TestDefaults::HASHED_PASSWORD)
                ->build();

            static::getContainer()->get('customer.repository')->create([$customer], $context);

            $orderIds = new IdsCollection();
            $order = (new OrderBuilder($orderIds, $orderNumber))
                ->price($amount)
                ->add('orderDateTime', (new \DateTimeImmutable('2025-01-' . \sprintf('%02d', $i + 10)))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
                ->add('orderCustomer', [
                    'id' => $orderIds->get('orderCustomer'),
                    'customerId' => $ids->get('cust-' . $i),
                    'firstName' => 'Revenue',
                    'lastName' => 'Test',
                    'email' => $email,
                ])
                ->addAddress('billing-address')
                ->addTransaction('transaction', ['amount' => $amount])
                ->build();

            static::getContainer()->get('order.repository')->upsert([$order], $context);
        }

        $output = ($this->revenueReportTool)(
            from: '2025-01-01',
            to: '2025-01-31',
            groupBy: 'day',
        );

        $data = $this->decodeToolOutput($output);

        static::assertGreaterThanOrEqual($expectedTotal, $data['data']['totalRevenue']);
        static::assertGreaterThanOrEqual(3, $data['data']['orderCount']);
        static::assertGreaterThan(0, $data['data']['averageOrderValue']);
        static::assertIsArray($data['data']['timeline']);
        static::assertNotEmpty($data['data']['timeline']);

        $firstBucket = $data['data']['timeline'][0];
        static::assertArrayHasKey('date', $firstBucket);
        static::assertArrayHasKey('revenue', $firstBucket);
    }

    public function testUS17BestsellerReport(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $productAId = $ids->create('productA');
        $productBId = $ids->create('productB');
        $productANumber = 'MCP-BS-A-' . Uuid::randomHex();
        $productBNumber = 'MCP-BS-B-' . Uuid::randomHex();

        static::getContainer()->get('product.repository')->create([
            [
                'id' => $productAId,
                'name' => 'Bestseller A',
                'productNumber' => $productANumber,
                'stock' => 100,
                'taxId' => $this->getValidTaxId(),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.0, 'net' => 8.40, 'linked' => true]],
            ],
            [
                'id' => $productBId,
                'name' => 'Bestseller B',
                'productNumber' => $productBNumber,
                'stock' => 100,
                'taxId' => $this->getValidTaxId(),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20.0, 'net' => 16.81, 'linked' => true]],
            ],
        ], $context);

        $lineItemSets = [
            ['productId' => $productAId, 'productNumber' => $productANumber, 'quantity' => 5, 'unitPrice' => 10.0],
            ['productId' => $productBId, 'productNumber' => $productBNumber, 'quantity' => 2, 'unitPrice' => 20.0],
            ['productId' => $productAId, 'productNumber' => $productANumber, 'quantity' => 3, 'unitPrice' => 10.0],
        ];

        foreach ($lineItemSets as $i => $lineItemData) {
            $orderNumber = 'MCP-US17-' . $i . '-' . Uuid::randomHex();
            $email = 'mcp-us17-' . $i . '-' . Uuid::randomHex() . '@example.com';

            $customer = (new CustomerBuilder($ids, 'cust-us17-' . $i))
                ->add('email', $email)
                ->add('password', TestDefaults::HASHED_PASSWORD)
                ->build();

            static::getContainer()->get('customer.repository')->create([$customer], $context);

            $orderIds = new IdsCollection();
            $totalPrice = $lineItemData['quantity'] * $lineItemData['unitPrice'];

            $order = (new OrderBuilder($orderIds, $orderNumber))
                ->price($totalPrice)
                ->add('orderDateTime', (new \DateTimeImmutable('2025-02-' . \sprintf('%02d', $i + 10)))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
                ->add('orderCustomer', [
                    'id' => $orderIds->get('orderCustomer'),
                    'customerId' => $ids->get('cust-us17-' . $i),
                    'firstName' => 'Bestseller',
                    'lastName' => 'Test',
                    'email' => $email,
                ])
                ->addAddress('billing-address')
                ->addTransaction('transaction', ['amount' => $totalPrice])
                ->add('lineItems', [[
                    'id' => Uuid::randomHex(),
                    'identifier' => Uuid::randomHex(),
                    'productId' => $lineItemData['productId'],
                    'referencedId' => $lineItemData['productId'],
                    'payload' => ['productNumber' => $lineItemData['productNumber']],
                    'label' => 'Product',
                    'type' => 'product',
                    'quantity' => $lineItemData['quantity'],
                    'unitPrice' => $lineItemData['unitPrice'],
                    'totalPrice' => $totalPrice,
                    'price' => ['unitPrice' => $lineItemData['unitPrice'], 'totalPrice' => $totalPrice, 'quantity' => $lineItemData['quantity'], 'calculatedTaxes' => [], 'taxRules' => []],
                    'position' => 1,
                ]])
                ->build();

            static::getContainer()->get('order.repository')->upsert([$order], $context);
        }

        $output = ($this->bestsellerReportTool)(
            from: '2025-02-01',
            to: '2025-02-28',
            limit: 10,
        );

        $data = $this->decodeToolOutput($output);

        static::assertNotEmpty($data['data']['bestsellers']);
        static::assertSame(['from' => '2025-02-01', 'to' => '2025-02-28'], $data['data']['period']);

        $productAEntry = null;
        $productBEntry = null;
        foreach ($data['data']['bestsellers'] as $entry) {
            if ($entry['productId'] === $productAId) {
                $productAEntry = $entry;
            }
            if ($entry['productId'] === $productBId) {
                $productBEntry = $entry;
            }
        }

        static::assertNotNull($productAEntry, 'Product A should appear in bestsellers');
        static::assertNotNull($productBEntry, 'Product B should appear in bestsellers');

        static::assertGreaterThanOrEqual(8, $productAEntry['totalQuantity']);
        static::assertGreaterThanOrEqual(2, $productBEntry['totalQuantity']);

        $productAIndex = array_search($productAId, array_column($data['data']['bestsellers'], 'productId'), true);
        $productBIndex = array_search($productBId, array_column($data['data']['bestsellers'], 'productId'), true);
        static::assertLessThan($productBIndex, $productAIndex, 'Product A should rank higher than Product B');
    }
}
