<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductCreateTool::class)]
#[CoversClass(StateMachineTransitionTool::class)]
#[CoversClass(EntityReadTool::class)]
class AdminOperationsScenarioTest extends McpScenarioTestCase
{
    public function testUS4ProductCreateDryRun(): void
    {
        $output = ($this->productCreateTool)(
            name: 'MCP Test Shirt',
            productNumber: 'MCP-US4-' . Uuid::randomHex(),
            grossPrice: 29.99,
            taxRate: 19,
            dryRun: true,
        );

        $data = $this->decodeToolOutput($output);

        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame('MCP Test Shirt', $data['data']['name']);
        static::assertNotEmpty($data['data']['taxId']);
        static::assertNotEmpty($data['data']['price']);
        static::assertEqualsWithDelta(29.99 / 1.19, $data['data']['price'][0]['net'], 0.01);
    }

    public function testUS4ProductCreateCommit(): void
    {
        $productNumber = 'MCP-US4-' . Uuid::randomHex();

        $output = ($this->productCreateTool)(
            name: 'MCP Commit Shirt',
            productNumber: $productNumber,
            grossPrice: 29.99,
            taxRate: 19,
            stock: 50,
            dryRun: false,
        );

        $data = $this->decodeToolOutput($output);

        static::assertFalse($data['_meta']['dryRun']);
        static::assertNotEmpty($data['data']['productId']);

        $readOutput = ($this->entityReadTool)('product', $data['data']['productId']);
        $readData = $this->decodeToolOutput($readOutput);

        static::assertSame('MCP Commit Shirt', $readData['data']['name']);
        static::assertSame(50, $readData['data']['stock']);
    }

    public function testUS5ShipOrderDelivery(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $orderNumber = 'MCP-US5-' . Uuid::randomHex();

        $deliveryId = $ids->create('delivery');

        $customer = (new CustomerBuilder($ids, 'US5-cust'))
            ->add('email', 'mcp-us5@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);

        $order = (new OrderBuilder($ids, $orderNumber))
            ->add('orderCustomer', [
                'id' => $ids->get('orderCustomer'),
                'customerId' => $ids->get('US5-cust'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'mcp-us5@example.com',
            ])
            ->addAddress('billing-address')
            ->addTransaction('transaction')
            ->add('deliveries', [[
                'id' => $deliveryId,
                'stateId' => $this->getStateMachineState(
                    OrderDeliveryStates::STATE_MACHINE,
                    OrderDeliveryStates::STATE_OPEN,
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

        $dryRunOutput = ($this->stateMachineTransitionTool)(
            entityName: 'order_delivery',
            entityId: $deliveryId,
            actionName: 'ship',
            dryRun: true,
        );

        $dryRunData = $this->decodeToolOutput($dryRunOutput);
        static::assertTrue($dryRunData['_meta']['dryRun']);
        static::assertTrue($dryRunData['data']['actionValid']);

        $commitOutput = ($this->stateMachineTransitionTool)(
            entityName: 'order_delivery',
            entityId: $deliveryId,
            actionName: 'ship',
            dryRun: false,
        );

        $commitData = $this->decodeToolOutput($commitOutput);
        static::assertFalse($commitData['_meta']['dryRun']);

        $readOutput = ($this->entityReadTool)('order_delivery', $deliveryId, json_encode([
            'associations' => ['stateMachineState' => []],
        ], \JSON_THROW_ON_ERROR));

        $readData = $this->decodeToolOutput($readOutput);
        static::assertSame('shipped', $readData['data']['stateMachineState']['technicalName']);
    }
}
