<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
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
                'editOrderUrl' => 'editOrderUrl',
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

        $mailTemplateId = $this->getMailTemplateId($connection, $mailTemplateTypeId);
        if ($mailTemplateId === null) {
            return;
        }

        $eventName = 'state_enter.order_transaction.state.failed';
        $flowId = $this->getFlowId($connection, $eventName);

        if ($flowId === null) {
            $flowId = Uuid::randomBytes();
            $connection->insert(
                'flow',
                [
                    'id' => $flowId,
                    'name' => 'Payment enters status failed',
                    'event_name' => $eventName,
                    'priority' => 1,
                    'invalid' => 0,
                    'active' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            );
        }

        if ($this->flowSequenceExists($connection, $flowId, 'action.mail.send')) {
            return;
        }

        $connection->insert(
            'flow_sequence',
            [
                'id' => Uuid::randomBytes(),
                'flow_id' => $flowId,
                'action_name' => 'action.mail.send',
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

        $this->registerIndexer($connection, 'flow.indexer');
    }

    private function getFlowId(Connection $connection, string $eventName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT id FROM flow WHERE event_name = :eventName',
            ['eventName' => $eventName],
        );

        return \is_string($result) ? $result : null;
    }

    private function flowSequenceExists(Connection $connection, string $flowId, string $actionName): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM flow_sequence WHERE flow_id = :flowId AND action_name = :actionName',
            [
                'flowId' => $flowId,
                'actionName' => $actionName,
            ],
        );
    }
}
