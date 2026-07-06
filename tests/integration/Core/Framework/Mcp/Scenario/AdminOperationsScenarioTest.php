<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class AdminOperationsScenarioTest extends McpScenarioTestCase
{
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

        $dryRunOutput = ($this->orderStateTool)(
            orderNumber: $orderNumber,
            deliveryAction: 'ship',
            dryRun: true,
        );

        $dryRunData = $this->decodeToolOutput($dryRunOutput);
        static::assertTrue($dryRunData['_meta']['dryRun']);
        static::assertTrue($dryRunData['data']['deliveries'][0]['actionValid']);

        $commitOutput = ($this->orderStateTool)(
            orderNumber: $orderNumber,
            deliveryAction: 'ship',
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

    public function testUS6CancelOrderWithRefund(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $orderNumber = 'MCP-US6-' . Uuid::randomHex();

        $deliveryId = $ids->create('delivery');

        $customer = (new CustomerBuilder($ids, 'US6-cust'))
            ->add('email', 'mcp-us6@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);

        $order = (new OrderBuilder($ids, $orderNumber))
            ->add('orderCustomer', [
                'id' => $ids->get('orderCustomer'),
                'customerId' => $ids->get('US6-cust'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'mcp-us6@example.com',
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

        static::getContainer()->get('order.repository')->upsert([$order], $context);

        $dryRunOutput = ($this->orderStateTool)(
            orderNumber: $orderNumber,
            orderAction: 'cancel',
            transactionAction: 'cancel',
            deliveryAction: 'cancel',
            dryRun: true,
        );

        $dryRunData = $this->decodeToolOutput($dryRunOutput);
        static::assertTrue($dryRunData['_meta']['dryRun']);
        static::assertSame('cancel', $dryRunData['data']['order']['action']);
        static::assertTrue($dryRunData['data']['order']['actionValid']);

        $commitOutput = ($this->orderStateTool)(
            orderNumber: $orderNumber,
            orderAction: 'cancel',
            transactionAction: 'cancel',
            deliveryAction: 'cancel',
            dryRun: false,
        );

        $commitData = $this->decodeToolOutput($commitOutput);
        static::assertFalse($commitData['_meta']['dryRun']);
        static::assertTrue($commitData['data']['order']['executed']);
        static::assertSame('cancelled', $commitData['data']['order']['to']);

        foreach ($commitData['data']['transactions'] as $tx) {
            static::assertTrue($tx['executed']);
            static::assertSame('cancelled', $tx['to']);
        }

        foreach ($commitData['data']['deliveries'] as $del) {
            static::assertTrue($del['executed']);
            static::assertSame('cancelled', $del['to']);
        }

        $searchOutput = ($this->entitySearchTool)('order', json_encode([
            'filter' => [['type' => 'equals', 'field' => 'orderNumber', 'value' => $orderNumber]],
            'associations' => ['stateMachineState' => []],
            'limit' => 1,
        ], \JSON_THROW_ON_ERROR));
        $searchData = $this->decodeToolOutput($searchOutput);
        static::assertSame('cancelled', $searchData['data'][0]['stateMachineState']['technicalName']);
    }
}
