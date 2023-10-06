<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-import-type SequenceData from Migration1648803451FixInvalidMigrationOfBusinessEventToFlow
 */
#[Package('core')]
class Migration1625583619MoveDataFromEventActionToFlow extends MigrationStep
{
    private const RECIPIENT_TYPE_DEFAULT = 'default';

    private const RECIPIENT_TYPE_CUSTOM = 'custom';

    public bool $internal = false;

    /**
     * @var array<string, string>
     */
    private array $ruleIds = [];

    /**
     * @var list<array<string, string|int>>
     */
    private array $ruleQueue = [];

    /**
     * @var list<array<string, string|int>>
     */
    private array $ruleConditionQueue = [];

    /**
     * @var list<array<string, string|int|null>>
     */
    private array $flowQueue = [];

    /**
     * @var list<SequenceData>
     */
    private array $flowSequenceQueue = [];

    /**
     * @var list<array<string, string>>
     */
    private array $salesChannelRuleQueue = [];

    public function getCreationTimestamp(): int
    {
        return 1625583619;
    }

    public function update(Connection $connection): void
    {
        if (!$this->internal) {
            return;
        }

        $columnNameInDb = $connection->fetchOne(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "event_action"
                AND COLUMN_NAME = "migrated_flow_id";'
        );

        if (!$columnNameInDb) {
            $connection->executeStatement('
                ALTER TABLE `event_action`
                ADD COLUMN `migrated_flow_id` BINARY(16) NULL AFTER `active`;
            ');
        }

        $this->insertFlow($connection);

        $this->registerIndexer($connection, 'flow.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function insertFlow(Connection $connection): void
    {
        // implement update
        $eventActions = $connection->fetchAllAssociative(
            'SELECT event_action.id as event_action_id,
                    event_action.title as title ,
                    event_action.event_name as event_name,
                    event_action.action_name as action_name,
                    event_action.config as config,
                    event_action.custom_fields as custom_fields,
                    event_action.active as active,
                    GROUP_CONCAT(DISTINCT LOWER(HEX(event_action_rule.rule_id))) as rule_ids,
                    GROUP_CONCAT(DISTINCT LOWER(HEX(event_action_sales_channel.sales_channel_id))) as sales_channel_ids
            FROM event_action
            LEFT JOIN event_action_rule ON event_action.id = event_action_rule.event_action_id
            LEFT JOIN event_action_sales_channel ON event_action.id = event_action_sales_channel.event_action_id
            WHERE event_action.action_name = :actionName
                AND JSON_EXTRACT(event_action.config, "$.mail_template_id") IS NOT NULL
                AND event_action.migrated_flow_id IS NULL
            GROUP BY event_action.id;',
            ['actionName' => SendMailAction::getName()]
        );

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $eventActionIds = [];
        foreach ($eventActions as $flowValue) {
            $flowSequences = [];
            $flowId = Uuid::randomBytes();
            $saleSChannelFlowSequenceId = null;
            if ($flowValue['sales_channel_ids'] !== null) {
                // get rule_id from sales_channel_rule if rule of $salesChannelIds already exists
                $ruleId = $this->getRuleBySalesChannelIds($connection, $flowValue['sales_channel_ids'], $createdAt);

                $saleSChannelFlowSequenceId = Uuid::randomBytes();
                // an if condition will be added for the new flow if event_action has specific sales channels
                $flowSequences[] = $this->buildSequenceData(
                    $saleSChannelFlowSequenceId,
                    $flowId,
                    $ruleId,
                    null,
                    null,
                    0,
                    $createdAt
                );
            }

            $flowSequenceParentId = $saleSChannelFlowSequenceId;
            if ($flowValue['rule_ids'] !== null) {
                $ruleIds = explode(',', (string) $flowValue['rule_ids']);
                // migrate multiple rules from event_action to the if conditions in the new flow
                foreach (Uuid::fromHexToBytesList($ruleIds) as $ruleId) {
                    $flowSequenceId = Uuid::randomBytes();

                    $flowSequences[] = $this->buildSequenceData(
                        $flowSequenceId,
                        $flowId,
                        $ruleId,
                        $flowSequenceParentId,
                        null,
                        $flowSequenceParentId === null ? 0 : 1,
                        $createdAt
                    );

                    $flowSequenceParentId = $flowSequenceId;
                }
            }

            // add a flow_sequence that contain action_name to trigger the new flow
            $flowSequences[] = $this->buildSequenceData(
                Uuid::randomBytes(),
                $flowId,
                null,
                $flowSequenceParentId,
                $flowValue['action_name'],
                $flowSequenceParentId === null ? 0 : 1,
                $createdAt,
                $this->getNewConfig($flowValue['config'])
            );

            foreach ($flowSequences as $flowSequence) {
                $this->flowSequenceQueue[] = $flowSequence;
            }

            $this->flowQueue[] = [
                'id' => $flowId,
                'name' => $flowValue['title'] ?? $this->getEventFullNameByEventName($flowValue['event_name']),
                'event_name' => $flowValue['event_name'],
                'active' => $flowValue['active'],
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => $flowValue['custom_fields'],
                'created_at' => $createdAt,
            ];

            $eventActionIds[] = [
                'flowId' => $flowId,
                'eventActionId' => $flowValue['event_action_id'],
            ];
        }

        $queue = new MultiInsertQueryQueue($connection);

        foreach ($this->ruleQueue as $data) {
            $queue->addInsert(RuleDefinition::ENTITY_NAME, $data);
        }

        foreach ($this->ruleConditionQueue as $data) {
            $queue->addInsert(RuleConditionDefinition::ENTITY_NAME, $data);
        }

        foreach ($this->flowQueue as $data) {
            $queue->addInsert(FlowDefinition::ENTITY_NAME, $data);
        }

        foreach ($this->flowSequenceQueue as $data) {
            $queue->addInsert(FlowSequenceDefinition::ENTITY_NAME, $data);
        }

        foreach ($this->salesChannelRuleQueue as $data) {
            $queue->addInsert('sales_channel_rule', $data);
        }

        $queue->execute();

        $update = new RetryableQuery(
            $connection,
            $connection->prepare('UPDATE `event_action` SET `active` = 0, `migrated_flow_id` = :flowId WHERE id = :eventActionId')
        );

        foreach ($eventActionIds as $eventAction) {
            $update->execute([
                'flowId' => $eventAction['flowId'],
                'eventActionId' => $eventAction['eventActionId'],
            ]);
        }
    }

    /**
     * @param array<string> $salesChannelIds
     */
    private function createSalesChannelRule(Connection $connection, array $salesChannelIds, string $createdAt): string
    {
        $salesChannelName = $this->getSalesChannelName($connection, $salesChannelIds);

        $ruleId = Uuid::randomBytes();
        $this->ruleQueue[] = [
            'id' => $ruleId,
            'name' => $salesChannelName,
            'priority' => 100,
            'created_at' => $createdAt,
        ];

        $ruleOrConditionId = Uuid::randomBytes();
        $this->ruleConditionQueue[] = [
            'id' => $ruleOrConditionId,
            'rule_id' => $ruleId,
            'type' => 'orContainer',
            'created_at' => $createdAt,
        ];

        $ruleAndConditionId = Uuid::randomBytes();
        $this->ruleConditionQueue[] = [
            'id' => $ruleAndConditionId,
            'rule_id' => $ruleId,
            'parent_id' => $ruleOrConditionId,
            'type' => 'andContainer',
            'created_at' => $createdAt,
        ];

        $this->ruleConditionQueue[] = [
            'id' => Uuid::randomBytes(),
            'rule_id' => $ruleId,
            'parent_id' => $ruleAndConditionId,
            'type' => 'salesChannel',
            'value' => json_encode([
                'operator' => '=',
                'salesChannelIds' => $salesChannelIds,
            ], \JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ];

        foreach (Uuid::fromHexToBytesList($salesChannelIds) as $salesChannelId) {
            $this->salesChannelRuleQueue[] = [
                'rule_id' => $ruleId,
                'sales_channel_id' => $salesChannelId,
            ];
        }

        return $ruleId;
    }

    private function getRuleBySalesChannelIds(Connection $connection, string $salesChannelIds, string $createdAt): string
    {
        [$salesChannelIds, $salesChannelIdString] = $this->createSortedSalesChannelIdsString($salesChannelIds);

        if (\array_key_exists($salesChannelIdString, $this->ruleIds)) {
            return $this->ruleIds[$salesChannelIdString];
        }

        $ruleId = $connection->fetchOne(
            'SELECT rule_id FROM sales_channel_rule WHERE sales_channel_id IN (:salesChannelIds)
            GROUP BY rule_id HAVING count(distinct sales_channel_id) = :numberSalesChannel;',
            [
                'salesChannelIds' => $salesChannelIds,
                'numberSalesChannel' => \count($salesChannelIds),
            ],
            ['salesChannelIds' => ArrayParameterType::STRING]
        );

        if (!$ruleId) {
            // create rule is one of $salesChannelIds
            $ruleId = $this->createSalesChannelRule($connection, $salesChannelIds, $createdAt);
        }

        $this->ruleIds[$salesChannelIdString] = $ruleId;

        return $ruleId;
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function createSortedSalesChannelIdsString(string $salesChannelIds): array
    {
        $salesChannelIds = explode(',', $salesChannelIds);
        sort($salesChannelIds);
        $salesChannelIdString = implode('|', $salesChannelIds);

        return [$salesChannelIds, $salesChannelIdString];
    }

    /**
     * @return SequenceData
     */
    private function buildSequenceData(
        string $id,
        string $flowId,
        ?string $ruleId,
        ?string $parentId,
        ?string $actionName,
        int $trueCase,
        string $createdAt,
        ?string $config = null
    ): array {
        return [
            'id' => $id,
            'flow_id' => $flowId,
            'rule_id' => $ruleId,
            'parent_id' => $parentId,
            'action_name' => $actionName,
            'position' => 1,
            'true_case' => $trueCase,
            'created_at' => $createdAt,
            'config' => $config,
        ];
    }

    /**
     * @param array<string> $salesChannelIds
     */
    private function getSalesChannelName(Connection $connection, array $salesChannelIds): string
    {
        $salesChannelName = $connection->fetchFirstColumn(
            'SELECT sales_channel_translation.name as name FROM sales_channel
            LEFT JOIN sales_channel_translation ON sales_channel.id = sales_channel_translation.sales_channel_id
            WHERE sales_channel_translation.language_id = :languageId
            AND sales_channel.id IN (:salesChannelIds)',
            [
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'salesChannelIds' => Uuid::fromHexToBytesList($salesChannelIds),
            ],
            ['salesChannelIds' => ArrayParameterType::STRING]
        );

        $result = 'Match one of saleschannels';
        if (\count($salesChannelName) === 1) {
            $result = $salesChannelName[0];
        }

        return $result;
    }

    private function getEventFullNameByEventName(string $eventName): string
    {
        $listEventName = [
            'checkout.customer.before.login' => 'Customer has requested login',
            'checkout.customer.changed-payment-method' => 'Customer changes payment',
            'checkout.customer.deleted' => 'Customer account deleted',
            'checkout.customer.double_opt_in_guest_order' => 'Guest account registered with double opt-in',
            'checkout.customer.double_opt_in_registration' => 'Customer account registered with double opt-in',
            'checkout.customer.guest_register' => 'Guest account registered',
            'checkout.customer.login' => 'Customer logs on',
            'checkout.customer.logout' => 'Customer logs off',
            'checkout.customer.register' => 'Customer account registered',
            'checkout.order.placed' => 'Order placed',
            'contact_form.send' => 'Contact form sent',
            'customer.group.registration.accepted' => 'Customer group registration request accepted',
            'customer.group.registration.declined' => 'Customer group registration request declined',
            'customer.recovery.request' => 'Customer requests new password',
            'mail.after.create.message' => 'Email created',
            'mail.before.send' => 'Email is being sent',
            'mail.sent' => 'Email sent',
            'newsletter.confirm' => 'Newsletter sign-up confirmed',
            'newsletter.register' => 'Newsletter sign-up registered',
            'newsletter.unsubscribe' => 'Newsletter unsubscribed',
            'newsletter.update' => 'Newsletter sign-up updated',
            'product_export.log' => 'Product export executed',
            'state_enter.order.state.cancelled' => 'Order enters status cancelled',
            'state_enter.order.state.completed' => 'Order enters status completed',
            'state_enter.order.state.in_progress' => 'Order enters status in progress',
            'state_enter.order.state.open' => 'Order enters status open',
            'state_enter.order_delivery.state.cancelled' => 'Shipment enters status cancelled',
            'state_enter.order_delivery.state.open' => 'Shipment enters status open',
            'state_enter.order_delivery.state.returned' => 'Shipment enters status returned',
            'state_enter.order_delivery.state.returned_partially' => 'Shipment enters status partially returned',
            'state_enter.order_delivery.state.shipped' => 'Shipment enters status shipped',
            'state_enter.order_delivery.state.shipped_partially' => 'Shipment enters status partially shipped',
            'state_enter.order_transaction.state.authorized' => 'Payment enters status authorised',
            'state_enter.order_transaction.state.cancelled' => 'Payment enters status cancelled',
            'state_enter.order_transaction.state.chargeback' => 'Payment enters status refunded',
            'state_enter.order_transaction.state.failed' => 'Payment enters status failed',
            'state_enter.order_transaction.state.in_progress' => 'Payment enters status in progress',
            'state_enter.order_transaction.state.open' => 'Payment enters status open',
            'state_enter.order_transaction.state.paid' => 'Payment enters status paid',
            'state_enter.order_transaction.state.paid_partially' => 'Payment enters status partially paid',
            'state_enter.order_transaction.state.refunded' => 'Payment enters status refunded',
            'state_enter.order_transaction.state.refunded_partially' => 'Payment enters status partially refunded',
            'state_enter.order_transaction.state.reminded' => 'Payment enters status reminder sent',
            'state_leave.order.state.cancelled' => 'Order leaves status cancelled',
            'state_leave.order.state.completed' => 'Order leaves status completed',
            'state_leave.order.state.in_progress' => 'Order leaves status in progress',
            'state_leave.order.state.open' => 'Order leaves status open',
            'state_leave.order_delivery.state.cancelled' => 'Shipment leaves status cancelled',
            'state_leave.order_delivery.state.open' => 'Shipment leaves status open',
            'state_leave.order_delivery.state.returned' => 'Shipment leaves status returned',
            'state_leave.order_delivery.state.returned_partially' => 'Shipment leaves status partially returned',
            'state_leave.order_delivery.state.shipped' => 'Shipment leaves status shipped',
            'state_leave.order_delivery.state.shipped_partially' => 'Shipment leaves status partially shipped',
            'state_leave.order_transaction.state.authorized' => 'Payment leaves status authorised',
            'state_leave.order_transaction.state.cancelled' => 'Payment leaves status cancelled',
            'state_leave.order_transaction.state.chargeback' => 'Payment leaves status refunded',
            'state_leave.order_transaction.state.failed' => 'Payment leaves status failed',
            'state_leave.order_transaction.state.in_progress' => 'Payment leaves status in progress',
            'state_leave.order_transaction.state.open' => 'Payment leaves status open',
            'state_leave.order_transaction.state.paid' => 'Payment leaves status paid',
            'state_leave.order_transaction.state.paid_partially' => 'Payment leaves status partially paid',
            'state_leave.order_transaction.state.refunded' => 'Payment leaves status refunded',
            'state_leave.order_transaction.state.refunded_partially' => 'Payment leaves status partially refunded',
            'state_leave.order_transaction.state.reminded' => 'Payment leaves status reminder sent',
            'user.recovery.request' => 'User recovery request sent',
        ];

        if (\array_key_exists($eventName, $listEventName)) {
            return $listEventName[$eventName];
        }

        return $eventName;
    }

    private function getNewConfig(string $config): string
    {
        $config = json_decode($config, true, 512, \JSON_THROW_ON_ERROR);

        $type = self::RECIPIENT_TYPE_DEFAULT;
        $recipients = [];
        if (\array_key_exists('recipients', $config)) {
            $type = self::RECIPIENT_TYPE_CUSTOM;
            $recipients = $config['recipients'];

            unset($config['recipients']);
        }

        $result = [];
        foreach ($config as $key => $value) {
            $key = lcfirst(implode('', array_map('ucfirst', explode('_', (string) $key))));
            $result[$key] = $value;
        }

        $result['recipient'] = [
            'data' => $recipients,
            'type' => $type,
        ];

        return (string) json_encode($result, \JSON_THROW_ON_ERROR);
    }
}
