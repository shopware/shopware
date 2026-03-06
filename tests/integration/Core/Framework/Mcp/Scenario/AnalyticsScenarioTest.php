<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
}
