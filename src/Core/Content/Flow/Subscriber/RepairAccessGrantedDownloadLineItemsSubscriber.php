<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0.0 - reason:remove-subscriber - temporary one-time repair for 6.7.8.0 download access regression
 *
 * @phpstan-import-type FlowHolder from \Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader
 */
#[Package('after-sales')]
final readonly class RepairAccessGrantedDownloadLineItemsSubscriber implements EventSubscriberInterface
{
    public const REPAIRED_ACCESS_GRANTED_LINE_ITEM = 'core.repaired_access_granted_line_item';

    private const PAID_EVENT_NAME = 'state_enter.order_transaction.state.paid';
    private const GRANT_DOWNLOAD_ACCESS_ACTION_NAME = 'action.grant.download.access';

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private Connection $connection,
        private EntityRepository $orderRepository,
        private AbstractFlowLoader $flowLoader,
        private FlowFactory $flowFactory,
        private FlowExecutor $flowExecutor,
        private AbstractKeyValueStorage $storage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'repair',
        ];
    }

    public function repair(UpdatePostFinishEvent $event): void
    {
        if ($this->storage->has(self::REPAIRED_ACCESS_GRANTED_LINE_ITEM)) {
            return;
        }

        try {
            $flowHolders = $this->resolveAffectedFlowHolders();
            if ($flowHolders === []) {
                $this->storage->set(self::REPAIRED_ACCESS_GRANTED_LINE_ITEM, '1');

                return;
            }

            $orderIds = $this->findFaultyOrderIds();
            if ($orderIds === []) {
                $this->storage->set(self::REPAIRED_ACCESS_GRANTED_LINE_ITEM, '1');

                return;
            }

            $repairedOrders = 0;
            foreach (array_chunk($orderIds, 50) as $orderIdChunk) {
                $criteria = (new Criteria($orderIdChunk))
                    ->addAssociations([
                        'orderCustomer.salutation',
                        'orderCustomer.customer',
                        'stateMachineState',
                        'deliveries.shippingMethod',
                        'deliveries.shippingOrderAddress.country',
                        'deliveries.shippingOrderAddress.countryState',
                        'salesChannel',
                        'language.locale',
                        'transactions.paymentMethod',
                        'transactions.stateMachineState',
                        'lineItems',
                        'lineItems.downloads.media',
                        'currency',
                        'addresses.country',
                        'addresses.countryState',
                        'tags',
                    ]);

                $orders = $this->orderRepository->search($criteria, $event->getContext())->getEntities();

                foreach ($orders as $order) {
                    $orderContext = $this->buildOrderContext($order, $event->getContext());

                    $paidStateEvent = new OrderStateMachineStateChangeEvent(
                        self::PAID_EVENT_NAME,
                        $order,
                        $orderContext
                    );

                    $storableFlow = $this->flowFactory->create($paidStateEvent);
                    $this->flowExecutor->executeFlows($flowHolders, $storableFlow);

                    ++$repairedOrders;
                }
            }

            $this->storage->set(self::REPAIRED_ACCESS_GRANTED_LINE_ITEM, '1');
            $event->appendPostUpdateMessage(
                sprintf(
                    'Repaired digital download access for %d order(s) using affected paid-order download flows.',
                    $repairedOrders
                )
            );
        } catch (\Throwable $exception) {
            // The update process must not fail because of this optional one-time repair.
            $this->logger->error('Failed to repair access_granted for downloadable order line items', ['exception' => $exception]);
        }
    }

    /**
     * @return list<string>
     */
    private function findAffectedFlowIds(): array
    {
        /** @var list<string> $flowIds */
        $flowIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
            SELECT DISTINCT LOWER(HEX(`flow`.`id`)) AS flow_id
            FROM `flow`
            INNER JOIN `flow_sequence` `flow_action`
                ON `flow_action`.`flow_id` = `flow`.`id`
                AND `flow_action`.`action_name` = :grantDownloadAccessAction
            INNER JOIN `flow_sequence` `flow_rule`
                ON `flow_rule`.`flow_id` = `flow`.`id`
                AND `flow_rule`.`rule_id` IS NOT NULL
            INNER JOIN `rule_condition`
                ON `rule_condition`.`rule_id` = `flow_rule`.`rule_id`
            WHERE `flow`.`active` = 1
              AND `flow`.`invalid` = 0
              AND `flow`.`event_name` = :paidEventName
              AND `rule_condition`.`type` = :deprecatedRuleType
              AND JSON_UNQUOTE(JSON_EXTRACT(`rule_condition`.`value`, '$.operator')) = '='
              AND JSON_UNQUOTE(JSON_EXTRACT(`rule_condition`.`value`, '$.productState')) = :downloadState
            SQL,
            [
                'grantDownloadAccessAction' => self::GRANT_DOWNLOAD_ACCESS_ACTION_NAME,
                'paidEventName' => self::PAID_EVENT_NAME,
                'deprecatedRuleType' => 'cartLineItemProductStates',
                'downloadState' => 'is-download',
            ]
        );

        return $flowIds;
    }

    /**
     * @return list<string>
     */
    private function findFaultyOrderIds(): array
    {
        /** @var list<string> $orderIds */
        $orderIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
            SELECT DISTINCT LOWER(HEX(`line_item`.`order_id`)) AS order_id
            FROM `order_line_item_download` `download`
            INNER JOIN `order_line_item` `line_item`
                ON `line_item`.`id` = `download`.`order_line_item_id`
                AND `line_item`.`version_id` = `download`.`order_line_item_version_id`
            INNER JOIN `order_transaction` `transaction`
                ON `transaction`.`order_id` = `line_item`.`order_id`
                AND `transaction`.`order_version_id` = `line_item`.`order_version_id`
            INNER JOIN `state_machine_state` `state`
                ON `state`.`id` = `transaction`.`state_id`
            INNER JOIN `state_machine` `state_machine`
                ON `state_machine`.`id` = `state`.`state_machine_id`
            WHERE `download`.`access_granted` = 0
              AND JSON_UNQUOTE(JSON_EXTRACT(`line_item`.`payload`, '$.productType')) = 'digital'
              AND `state_machine`.`technical_name` = 'order_transaction.state'
              AND `state`.`technical_name` = 'paid'
            SQL
        );

        return $orderIds;
    }

    /**
     * @return list<FlowHolder>
     */
    private function resolveAffectedFlowHolders(): array
    {
        $affectedFlowIds = array_flip($this->findAffectedFlowIds());
        if ($affectedFlowIds === []) {
            return [];
        }

        $flowHolders = $this->flowLoader->load()[self::PAID_EVENT_NAME] ?? [];

        return array_values(array_filter(
            $flowHolders,
            static fn (array $holder): bool => isset($affectedFlowIds[$holder['id']])
        ));
    }

    private function buildOrderContext(OrderEntity $order, Context $context): Context
    {
        /** @var CashRoundingConfig $itemRounding */
        $itemRounding = $order->getItemRounding();

        $orderContext = new Context(
            $context->getSource(),
            $order->getRuleIds() ?? [],
            $order->getCurrencyId(),
            array_values(array_unique(array_merge([$order->getLanguageId()], $context->getLanguageIdChain()))),
            $context->getVersionId(),
            $order->getCurrencyFactor(),
            true,
            $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus(),
            $itemRounding
        );

        $orderContext->addState(...$context->getStates());
        $orderContext->addExtensions($context->getExtensions());

        return $orderContext;
    }
}
