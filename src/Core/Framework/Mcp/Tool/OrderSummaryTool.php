<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-order-summary', description: 'Look up an order by order number or UUID and get a pre-formatted summary with customer info, line items, payment status, and delivery status. Provide either orderNumber (e.g. "10001") or orderId (UUID). Returns {success, data: {id, orderNumber, status, amountTotal, customer, lineItems, transactions, deliveries}}.')]
#[Package('framework')]
class OrderSummaryTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $orderNumber = '', string $orderId = ''): string
    {
        if ($orderNumber === '' && $orderId === '') {
            return $this->error('Provide either orderNumber or orderId.');
        }

        $context = $this->contextProvider->getContext();

        if (!$context->isAllowed('order:read')) {
            return $this->error('Missing privilege: order:read');
        }

        $repository = $this->registry->getRepository('order');

        $criteria = $orderId !== ''
            ? new Criteria([$orderId])
            : new Criteria();

        if ($orderId === '' && $orderNumber !== '') {
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        }

        $criteria->setLimit(1);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');

        $result = $repository->search($criteria, $context);

        $order = $result->first();

        if (!$order instanceof OrderEntity) {
            return $this->error('Order not found.');
        }

        $lineItems = [];
        foreach ($order->getLineItems()?->getElements() ?? [] as $item) {
            \assert($item instanceof OrderLineItemEntity);
            $lineItems[] = [
                'label' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'totalPrice' => $item->getTotalPrice(),
                'productId' => $item->getProductId(),
            ];
        }

        $transactions = [];
        foreach ($order->getTransactions()?->getElements() ?? [] as $tx) {
            \assert($tx instanceof OrderTransactionEntity);
            $transactions[] = [
                'status' => $tx->getStateMachineState()?->getTechnicalName(),
                'amount' => $tx->getAmount()->getTotalPrice(),
            ];
        }

        $deliveries = [];
        foreach ($order->getDeliveries()?->getElements() ?? [] as $delivery) {
            \assert($delivery instanceof OrderDeliveryEntity);
            $deliveries[] = [
                'status' => $delivery->getStateMachineState()?->getTechnicalName(),
            ];
        }

        return $this->success([
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'orderDateTime' => $order->getOrderDateTime()->format('c'),
            'status' => $order->getStateMachineState()?->getTechnicalName(),
            'amountTotal' => $order->getAmountTotal(),
            'amountNet' => $order->getAmountNet(),
            'shippingTotal' => $order->getShippingTotal(),
            'currencyIsoCode' => $order->getCurrency()?->getIsoCode(),
            'customer' => [
                'email' => $order->getOrderCustomer()?->getEmail(),
                'firstName' => $order->getOrderCustomer()?->getFirstName(),
                'lastName' => $order->getOrderCustomer()?->getLastName(),
            ],
            'lineItems' => $lineItems,
            'transactions' => $transactions,
            'deliveries' => $deliveries,
        ]);
    }
}
