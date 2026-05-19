<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-order-state', title: 'Order State', description: 'Change the state of an order, its transactions, and/or its deliveries in one call. Looks up the order by orderNumber or orderId. Provide at least one of orderAction, transactionAction, or deliveryAction. Common actions: cancel, process, complete, reopen, paid, refund, ship, retour. Always use dryRun=true (default) to preview available transitions before executing with dryRun=false. See shopware://state-machines resource for all valid states and transitions.')]
#[McpToolRequires('order:read')]
#[McpToolRequires('order:update')]
#[McpToolRequires('order_transaction:update')]
#[McpToolRequires('order_delivery:update')]
#[Package('framework')]
class OrderStateTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        string $orderNumber = '',
        string $orderId = '',
        string $orderAction = '',
        string $transactionAction = '',
        string $deliveryAction = '',
        bool $dryRun = true,
    ): string {
        if ($orderNumber === '' && $orderId === '') {
            return $this->error('Provide either orderNumber or orderId.');
        }

        if ($orderAction === '' && $transactionAction === '' && $deliveryAction === '') {
            return $this->error('Provide at least one of orderAction, transactionAction, or deliveryAction.');
        }

        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, OrderDefinition::ENTITY_NAME . ':read')) {
            return $error;
        }

        if (!$dryRun) {
            $writePrivileges = [];
            if ($orderAction !== '') {
                $writePrivileges[] = OrderDefinition::ENTITY_NAME . ':update';
            }
            if ($transactionAction !== '') {
                $writePrivileges[] = OrderTransactionDefinition::ENTITY_NAME . ':update';
            }
            if ($deliveryAction !== '') {
                $writePrivileges[] = OrderDeliveryDefinition::ENTITY_NAME . ':update';
            }

            if ($writePrivileges !== [] && ($error = $this->requirePrivilege($context, ...$writePrivileges))) {
                return $error;
            }
        }

        $order = $this->loadOrder($orderId, $orderNumber, $context);

        if (!$order instanceof OrderEntity) {
            return $this->error('Order not found.');
        }

        $base = [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
        ];

        if ($dryRun) {
            $transitionResult = $this->applyTransitions($order, $orderAction, $transactionAction, $deliveryAction, $context, true);
        } else {
            // Wrap all transitions in one transaction so a mid-sequence failure rolls back prior changes.
            // Nested DAL transactions are handled via DBAL savepoints. Note: Redis cache invalidations
            // enqueued during the operation are NOT rolled back on DB rollback.
            $transitionResult = $this->connection->transactional(
                fn () => $this->applyTransitions($order, $orderAction, $transactionAction, $deliveryAction, $context, false)
            );
        }

        return $this->success(array_merge($base, $transitionResult), ['dryRun' => $dryRun]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applyTransitions(
        OrderEntity $order,
        string $orderAction,
        string $transactionAction,
        string $deliveryAction,
        Context $context,
        bool $dryRun,
    ): array {
        $result = [];

        if ($orderAction !== '') {
            $result['order'] = $this->resolveTransition(
                OrderDefinition::ENTITY_NAME,
                $order->getId(),
                $orderAction,
                $order->getStateMachineState()?->getTechnicalName() ?? 'unknown',
                $context,
                $dryRun,
            );
        }

        if ($transactionAction !== '') {
            $result['transactions'] = $this->applyToTransactions($order, $transactionAction, $context, $dryRun);
        }

        if ($deliveryAction !== '') {
            $result['deliveries'] = $this->applyToDeliveries($order, $deliveryAction, $context, $dryRun);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function applyToTransactions(OrderEntity $order, string $action, Context $context, bool $dryRun): array
    {
        $results = [];
        foreach ($order->getTransactions()?->getElements() ?? [] as $tx) {
            \assert($tx instanceof OrderTransactionEntity);
            $currentState = $tx->getStateMachineState()?->getTechnicalName() ?? 'unknown';

            $results[] = $this->resolveTransition(OrderTransactionDefinition::ENTITY_NAME, $tx->getId(), $action, $currentState, $context, $dryRun);
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function applyToDeliveries(OrderEntity $order, string $action, Context $context, bool $dryRun): array
    {
        $results = [];
        foreach ($order->getDeliveries()?->getElements() ?? [] as $delivery) {
            \assert($delivery instanceof OrderDeliveryEntity);
            $currentState = $delivery->getStateMachineState()?->getTechnicalName() ?? 'unknown';

            $results[] = $this->resolveTransition(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $action, $currentState, $context, $dryRun);
        }

        return $results;
    }

    private function loadOrder(string $orderId, string $orderNumber, Context $context): ?OrderEntity
    {
        $repository = $this->registry->getRepository(OrderDefinition::ENTITY_NAME);

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
     * @return array<string, mixed>
     */
    private function resolveTransition(
        string $entityName,
        string $entityId,
        string $action,
        string $currentState,
        Context $context,
        bool $dryRun,
    ): array {
        $availableTransitions = $this->getAvailableTransitions($entityName, $entityId, $context);

        $targetState = null;
        $actionValid = false;
        foreach ($availableTransitions as $t) {
            if ($t['actionName'] === $action) {
                $actionValid = true;
                $targetState = $t['toStateName'];

                break;
            }
        }

        if ($dryRun) {
            return [
                'id' => $entityId,
                'from' => $currentState,
                'action' => $action,
                'actionValid' => $actionValid,
                'availableTransitions' => $availableTransitions,
            ];
        }

        if (!$actionValid) {
            return [
                'id' => $entityId,
                'from' => $currentState,
                'action' => $action,
                'executed' => false,
                'note' => \sprintf('Transition "%s" not available from state "%s"', $action, $currentState),
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

    /**
     * @return list<array{actionName: string, toStateName: string|null}>
     */
    private function getAvailableTransitions(string $entityName, string $entityId, Context $context): array
    {
        try {
            $transitions = $this->stateMachineRegistry->getAvailableTransitions($entityName, $entityId, 'stateId', $context);
        } catch (\Throwable) {
            return [];
        }

        $available = [];
        foreach ($transitions as $transition) {
            $available[] = [
                'actionName' => $transition->getActionName(),
                'toStateName' => $transition->getToStateMachineState()?->getTechnicalName(),
            ];
        }

        return $available;
    }
}
