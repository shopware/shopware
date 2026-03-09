<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-order-cancel', description: 'Cancel an order including its transactions and deliveries in one call. Looks up the order by orderNumber or orderId, then cancels the order, refunds or cancels each transaction, and cancels each delivery. Always use dryRun=true (default) to preview before executing with dryRun=false. Set refundTransactions=true to refund paid transactions instead of cancelling them.')]
#[Package('framework')]
class OrderCancelTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly StateMachineRegistry $stateMachineRegistry,
    ) {
    }

    public function __invoke(
        string $orderNumber = '',
        string $orderId = '',
        bool $refundTransactions = false,
        bool $dryRun = true,
    ): string {
        if ($orderNumber === '' && $orderId === '') {
            return $this->error('Provide either orderNumber or orderId.');
        }

        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'order:read')) {
            return $error;
        }

        if (!$dryRun) {
            if ($error = $this->requirePrivilege($context, 'order:update', 'order_transaction:update', 'order_delivery:update')) {
                return $error;
            }
        }

        $order = $this->loadOrder($orderId, $orderNumber, $context);

        if (!$order instanceof OrderEntity) {
            return $this->error('Order not found.');
        }

        $orderResult = $this->resolveTransition(
            'order',
            $order->getId(),
            'cancel',
            $order->getStateMachineState()?->getTechnicalName() ?? 'unknown',
            $context,
            $dryRun,
        );

        $transactionResults = $this->cancelTransactions($order, $refundTransactions, $context, $dryRun);
        $deliveryResults = $this->cancelDeliveries($order, $context, $dryRun);

        return $this->success([
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'order' => $orderResult,
            'transactions' => $transactionResults,
            'deliveries' => $deliveryResults,
        ], ['dryRun' => $dryRun]);
    }

    /**
     * @return list<array{id: string, from: string, to: string, action: string, executed: bool, note?: string}>
     */
    private function cancelTransactions(OrderEntity $order, bool $refundTransactions, Context $context, bool $dryRun): array
    {
        $results = [];
        foreach ($order->getTransactions()?->getElements() ?? [] as $tx) {
            \assert($tx instanceof OrderTransactionEntity);
            $currentState = $tx->getStateMachineState()?->getTechnicalName() ?? 'unknown';
            $action = $this->resolveTransactionAction($currentState, $refundTransactions);

            $results[] = $this->resolveTransition('order_transaction', $tx->getId(), $action, $currentState, $context, $dryRun);
        }

        return $results;
    }

    /**
     * @return list<array{id: string, from: string, to: string, action: string, executed: bool, note?: string}>
     */
    private function cancelDeliveries(OrderEntity $order, Context $context, bool $dryRun): array
    {
        $results = [];
        foreach ($order->getDeliveries()?->getElements() ?? [] as $delivery) {
            \assert($delivery instanceof OrderDeliveryEntity);
            $currentState = $delivery->getStateMachineState()?->getTechnicalName() ?? 'unknown';

            $results[] = $this->resolveTransition('order_delivery', $delivery->getId(), 'cancel', $currentState, $context, $dryRun);
        }

        return $results;
    }

    private function loadOrder(string $orderId, string $orderNumber, Context $context): ?OrderEntity
    {
        $repository = $this->registry->getRepository('order');

        $criteria = $orderId !== ''
            ? new Criteria([$orderId])
            : new Criteria();

        if ($orderId === '' && $orderNumber !== '') {
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        }

        $criteria->setLimit(1);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->addAssociation('stateMachineState');

        $result = $repository->search($criteria, $context);
        $order = $result->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    /**
     * @return array{id: string, from: string, to: string, action: string, executed: bool, note?: string}
     */
    private function resolveTransition(
        string $entityName,
        string $entityId,
        string $action,
        string $currentState,
        Context $context,
        bool $dryRun,
    ): array {
        $targetState = $this->getTargetState($action);

        if ($currentState === $targetState) {
            return [
                'id' => $entityId,
                'from' => $currentState,
                'to' => $targetState,
                'action' => $action,
                'executed' => false,
                'note' => 'Already in target state',
            ];
        }

        if (!$this->isTransitionAvailable($entityName, $entityId, $action, $context)) {
            return [
                'id' => $entityId,
                'from' => $currentState,
                'to' => $targetState,
                'action' => $action,
                'executed' => false,
                'note' => \sprintf('Transition "%s" not available from state "%s"', $action, $currentState),
            ];
        }

        if ($dryRun) {
            return [
                'id' => $entityId,
                'from' => $currentState,
                'to' => $targetState,
                'action' => $action,
                'executed' => false,
                'note' => 'Will execute on commit',
            ];
        }

        $transition = new Transition($entityName, $entityId, $action, 'stateId');
        $this->stateMachineRegistry->transition($transition, $context);

        return [
            'id' => $entityId,
            'from' => $currentState,
            'to' => $targetState,
            'action' => $action,
            'executed' => true,
        ];
    }

    private function isTransitionAvailable(string $entityName, string $entityId, string $action, Context $context): bool
    {
        try {
            $transitions = $this->stateMachineRegistry->getAvailableTransitions($entityName, $entityId, 'stateId', $context);
        } catch (\Throwable) {
            return false;
        }

        foreach ($transitions as $transition) {
            if ($transition->getActionName() === $action) {
                return true;
            }
        }

        return false;
    }

    private function resolveTransactionAction(string $currentState, bool $refundTransactions): string
    {
        if ($refundTransactions && \in_array($currentState, ['paid', 'paid_partially', 'authorized'], true)) {
            return 'refund';
        }

        return 'cancel';
    }

    private function getTargetState(string $action): string
    {
        return match ($action) {
            'refund' => 'refunded',
            'cancel' => 'cancelled',
            default => $action,
        };
    }
}
