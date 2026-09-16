<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;
use Shopware\Core\Migration\Traits\CreateMailTemplateTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1786967900AddFailedPaymentMailTemplateAndFlow extends MigrationStep
{
    use CreateMailTemplateTrait;

    final public const FLOW_ID = '0accf0ae04844231af7e785e8dc94f65';

    public function getCreationTimestamp(): int
    {
        return 1786967900;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED,
            'Enter payment state: Failed',
            'Eintritt Zahlungsstatus: Fehlgeschlagen',
            [
                'order' => 'order',
                'previousState' => 'state_machine_state',
                'newState' => 'state_machine_state',
                'salesChannel' => 'sales_channel',
            ],
        );

        $mailTemplate = new MailTemplateCreateStruct(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED,
            'The payment for your order has failed',
            'Die Zahlung für Ihre Bestellung ist fehlgeschlagen',
            'Payment status changed to failed',
            'Der Zahlungsstatus hat sich auf fehlgeschlagen geändert',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->createMail($connection, $mailTemplateType, $mailTemplate);

        $mailTemplateTypeId = $this->getMailTemplateTypeId(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED,
        );

        if ($mailTemplateTypeId === null) {
            return;
        }

        $mailTemplateId = $this->getMailTemplateId($connection, $mailTemplateTypeId);
        if ($mailTemplateId === null) {
            return;
        }

        $eventName = 'state_enter.order_transaction.state.failed';
        $binaryFlowId = Uuid::fromHexToBytes(self::FLOW_ID);

        $flowExists = $this->flowExists($connection);
        if (!$flowExists) {
            $connection->insert(
                'flow',
                [
                    'id' => $binaryFlowId,
                    'name' => 'Payment enters status failed',
                    'event_name' => $eventName,
                    'priority' => 1,
                    'invalid' => 0,
                    'active' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            );
        }

        $flowSequenceId = $this->getFlowSequenceId($connection, $binaryFlowId, SendMailAction::ACTION_NAME);
        if ($flowSequenceId === null) {
            $flowSequenceId = Uuid::randomBytes();

            $connection->insert(
                'flow_sequence',
                [
                    'id' => $flowSequenceId,
                    'flow_id' => $binaryFlowId,
                    'action_name' => SendMailAction::ACTION_NAME,
                    'config' => json_encode([
                        'replyTo' => null,
                        'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateId),
                        'documentTypeIds' => [],
                        'recipient' => [
                            'data' => [],
                            'type' => 'default',
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    'display_group' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            );
        }

        $this->createFlowTemplate($connection, $flowSequenceId, $mailTemplateId, $mailTemplateTypeId, $eventName);
        $this->registerIndexer($connection, 'flow.indexer');
    }

    private function createFlowTemplate(Connection $connection, string $flowSequenceId, string $mailTemplateId, string $mailTemplateTypeId, string $eventName): void
    {
        $flowTemplateId = $connection->fetchOne(
            'SELECT `id` FROM `flow_template` WHERE JSON_EXTRACT(`config`, \'$.eventName\') = :eventName',
            ['eventName' => $eventName],
        );

        if ($flowTemplateId) {
            return;
        }

        $connection->insert(
            FlowTemplateDefinition::ENTITY_NAME,
            [
                'id' => Uuid::randomBytes(),
                'name' => 'Payment enters status failed',
                'config' => json_encode([
                    'eventName' => $eventName,
                    'description' => null,
                    'customFields' => null,
                    'sequences' => [
                        [
                            'id' => Uuid::fromBytesToHex($flowSequenceId),
                            'actionName' => SendMailAction::ACTION_NAME,
                            'config' => [
                                'recipient' => [
                                    'data' => [],
                                    'type' => 'default',
                                ],
                                'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateId),
                                'mailTemplateTypeId' => Uuid::fromBytesToHex($mailTemplateTypeId),
                                'documentTypeIds' => [],
                            ],
                            'parentId' => null,
                            'ruleId' => null,
                            'position' => 1,
                            'trueCase' => 0,
                            'displayGroup' => 1,
                        ],
                    ],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    private function flowExists(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM flow WHERE id = :flowId',
            ['flowId' => Uuid::fromHexToBytes(self::FLOW_ID)],
        );
    }

    private function getFlowSequenceId(Connection $connection, string $flowId, string $actionName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `flow_sequence` WHERE `flow_id` = :flowId AND `action_name` = :actionName',
            [
                'flowId' => $flowId,
                'actionName' => $actionName,
            ],
        );

        return \is_string($result) ? $result : null;
    }
}
