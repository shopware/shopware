<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\ParameterBag;

class SetOrderStateAction extends FlowAction
{
    private Connection $connection;

    private LoggerInterface $logger;

    private StateMachineRegistry $stateMachineRegistry;

    private OrderService $orderService;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        StateMachineRegistry $stateMachineRegistry,
        OrderService $orderService
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderService = $orderService;
    }

    public static function getName(): string
    {
        return 'action.set.order.state';
    }

    public static function getSubscribedEvents()
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();

        if (empty($config)) {
            return;
        }

        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof OrderAware) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            if (\array_key_exists('order_transaction', $config) && $config['order_transaction']) {
                $this->setOrderTransactionState($baseEvent, $config);
            }

            if (\array_key_exists('order_delivery', $config) && $config['order_delivery']) {
                $this->setOrderDeliveryState($baseEvent, $config);
            }

            if (\array_key_exists('order', $config) && $config['order']) {
                $this->setOrderState($baseEvent, $config);
            }

            $this->connection->commit();
        } catch (ShopwareHttpException $e) {
            $this->connection->rollBack();
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @throws IllegalTransitionException
     */
    private function setOrderState(OrderAware $baseEvent, array $config): void
    {
        $orderId = $baseEvent->getOrderId();

        $possibleTransitions = $this->getPossibleTransitions($baseEvent, 'order', $orderId);
        if (!isset($possibleTransitions[$config['order']])) {
            $fromStateId = $this->getOrderStateFromId($orderId);

            throw new IllegalTransitionException(
                $fromStateId,
                $config['order'],
                array_values($possibleTransitions)
            );
        }

        $this->orderService->orderStateTransition(
            $orderId,
            $possibleTransitions[$config['order']],
            new ParameterBag(),
            $baseEvent->getContext()
        );
    }

    /**
     * @throws IllegalTransitionException
     */
    private function setOrderDeliveryState(OrderAware $baseEvent, array $config): void
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('id');
        $query->from('order_delivery');
        $query->where('`order_id` = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($baseEvent->getOrderId()));
        $orderDeliveryId = $query->execute()->fetchColumn();

        if (!$orderDeliveryId) {
            throw new IllegalTransitionException(
                '',
                $config['order_delivery'],
                []
            );
        }

        $orderDeliveryId = Uuid::fromBytesToHex($orderDeliveryId);
        $possibleTransitions = $this->getPossibleTransitions($baseEvent, 'order_delivery', $orderDeliveryId);

        if (!isset($possibleTransitions[$config['order_delivery']])) {
            $fromStateId = $this->getOrderDeliveryFromStateId($orderDeliveryId);

            throw new IllegalTransitionException(
                $fromStateId,
                $config['order_delivery'],
                array_values($possibleTransitions)
            );
        }

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            $possibleTransitions[$config['order_delivery']],
            new ParameterBag(),
            $baseEvent->getContext()
        );
    }

    /**
     * @throws IllegalTransitionException
     */
    private function setOrderTransactionState(OrderAware $baseEvent, array $config): void
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('id');
        $query->from('order_transaction');
        $query->where('`order_id` = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($baseEvent->getOrderId()));
        $orderTransactionId = $query->execute()->fetchColumn();

        if (!$orderTransactionId) {
            throw new IllegalTransitionException(
                '',
                $config['order_transaction'],
                []
            );
        }

        $orderTransactionId = Uuid::fromBytesToHex($orderTransactionId);
        $possibleTransitions = $this->getPossibleTransitions($baseEvent, 'order_transaction', $orderTransactionId);

        if (!isset($possibleTransitions[$config['order_transaction']])) {
            $fromStateId = $this->getOrderTransactionFromStateId($orderTransactionId);

            throw new IllegalTransitionException(
                $fromStateId,
                $config['order_transaction'],
                array_values($possibleTransitions)
            );
        }

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            $possibleTransitions[$config['order_transaction']],
            new ParameterBag(),
            $baseEvent->getContext()
        );
    }

    private function getPossibleTransitions(OrderAware $baseEvent, string $entityName, string $entityId): array
    {
        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
            $entityName,
            $entityId,
            'stateId',
            $baseEvent->getContext()
        );

        $possibleTransitions = [];
        foreach ($availableTransitions as $availableTransition) {
            $possibleTransitions[$availableTransition->getToStateMachineState()->getTechnicalName()] = $availableTransition->getActionName();
        }

        return $possibleTransitions;
    }

    private function getOrderTransactionFromStateId(string $orderTransactionId): string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('sms.id');
        $query->from('order_transaction', 'ot');
        $query->join('ot', 'state_machine_state', 'sms', 'ot.state_id = sms.id');
        $query->where('ot.id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderTransactionId));

        if (!$id = $query->execute()->fetchColumn()) {
            return '';
        }

        return UUID::fromBytesToHex($id);
    }

    private function getOrderDeliveryFromStateId(string $orderDeliveryId): string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('sms.id');
        $query->from('order_delivery', 'od');
        $query->join('od', 'state_machine_state', 'sms', 'sms.id = od.state_id');
        $query->where('od.id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderDeliveryId));

        if (!$id = $query->execute()->fetchColumn()) {
            return '';
        }

        return UUID::fromBytesToHex($id);
    }

    private function getOrderStateFromId(string $orderId): string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('state_id');
        $query->from('`order`');
        $query->where('`order`.id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));

        if (!$id = $query->execute()->fetchColumn()) {
            return '';
        }

        return UUID::fromBytesToHex($id);
    }
}
