<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Event\DelayAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Symfony\Component\HttpFoundation\ParameterBag;

class SetOrderStateAction extends FlowAction
{
    public const FORCE_TRANSITION = 'force_transition';

    private const ORDER = 'order';

    private const ORDER_DELIVERY = 'order_delivery';

    private const ORDER_TRANSACTION = 'order_transaction';

    private Connection $connection;

    private LoggerInterface $logger;

    private OrderService $orderService;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        OrderService $orderService
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->orderService = $orderService;
    }

    public static function getName(): string
    {
        return 'action.set.order.state';
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class, DelayAware::class];
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

        $context = $baseEvent->getContext();
        if ($config[self::FORCE_TRANSITION] ?? false) {
            $context->addState(self::FORCE_TRANSITION);
        }

        $this->connection->beginTransaction();

        try {
            $orderId = $baseEvent->getOrderId();
            $transitions = array_filter([
                self::ORDER => $config[self::ORDER] ?? null,
                self::ORDER_DELIVERY => $config[self::ORDER_DELIVERY] ?? null,
                self::ORDER_TRANSACTION => $config[self::ORDER_TRANSACTION] ?? null,
            ]);

            foreach ($transitions as $machine => $toPlace) {
                $this->transitState((string) $machine, $orderId, (string) $toPlace, $context);
            }

            $this->connection->commit();
        } catch (ShopwareHttpException $e) {
            $this->connection->rollBack();
            $this->logger->error($e->getMessage());
        } finally {
            $context->removeState(self::FORCE_TRANSITION);
        }
    }

    /**
     * @throws IllegalTransitionException
     * @throws StateMachineNotFoundException
     */
    private function transitState(string $machine, string $orderId, string $toPlace, Context $context): void
    {
        if (!$toPlace) {
            return;
        }

        $data = new ParameterBag();
        $machineId = $machine === self::ORDER ? $orderId : $this->getMachineId($machine, $orderId);
        if (!$machineId) {
            throw new StateMachineNotFoundException($machine);
        }

        $actionName = $this->getAvailableActionName($machine, $machineId, $toPlace);
        if (!$actionName) {
            $actionName = $toPlace;
        }

        switch ($machine) {
            case self::ORDER:
                $this->orderService->orderStateTransition($orderId, $actionName, $data, $context);

                return;
            case self::ORDER_DELIVERY:
                $this->orderService->orderDeliveryStateTransition($machineId, $actionName, $data, $context);

                return;
            case self::ORDER_TRANSACTION:
                $this->orderService->orderTransactionStateTransition($machineId, $actionName, $data, $context);

                return;
            default:
                throw new StateMachineNotFoundException($machine);
        }
    }

    private function getMachineId(string $machine, string $orderId): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(id))');
        $query->from($machine);
        $query->where('`order_id` = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));

        return $query->execute()->fetchOne() ?: null;
    }

    private function getAvailableActionName(string $machine, string $machineId, string $toPlace): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('action_name');
        $query->from('state_machine_transition');
        $query->where('from_state_id = :fromStateId');
        $query->andWhere('to_state_id = :toPlaceId');
        $query->setParameters([
            'fromStateId' => $this->getFromPlaceId($machine, $machineId),
            'toPlaceId' => $this->getToPlaceId($toPlace, $machine),
        ]);

        return $query->execute()->fetchOne() ?: null;
    }

    private function getToPlaceId(string $toPlace, string $machine): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('id');
        $query->from('state_machine_state');
        $query->where('technical_name = :toPlace');
        $query->andWhere('state_machine_id = :stateMachineId');
        $query->setParameters([
            'toPlace' => $toPlace,
            'stateMachineId' => $this->getStateMachineId($machine),
        ]);

        return $query->execute()->fetchOne() ?: null;
    }

    private function getFromPlaceId(string $machine, string $machineId): ?string
    {
        $escaped = EntityDefinitionQueryHelper::escape($machine);
        $query = $this->connection->createQueryBuilder();
        $query->select('state_id');
        $query->from($escaped);
        $query->where('id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($machineId));

        return $query->execute()->fetchOne() ?: null;
    }

    private function getStateMachineId(string $machine): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('id');
        $query->from('state_machine');
        $query->where('technical_name = :technicalName');
        $query->setParameter('technicalName', $machine . '.state');

        return $query->execute()->fetchOne() ?: null;
    }
}
