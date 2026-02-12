<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;
use Shopware\Core\Migration\Traits\CreateMailTemplateTrait;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate extends MigrationStep
{
    use CreateMailTemplateTrait;
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1770705203;
    }

    public function update(Connection $connection): void
    {
        // update mail templates
        $cancelledMailUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED);
        $cancelledMailUpdate->loadByDirectoryName(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED);
        $this->updateMail($cancelledMailUpdate, $connection);

        $remindedMailUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $remindedMailUpdate->loadByDirectoryName(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $this->updateMail($remindedMailUpdate, $connection);

        // create new mail template
        $mailTemplateType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED,
            'Payment method changed',
            'Zahlungsart geändert',
        );

        $mailTemplate = new MailTemplateCreateStruct(
            MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED,
            'Payment method changed',
            'Zahlungsart geändert',
            'Confirmation of payment method change',
            'Bestätigung der Änderung der Zahlungsmethode',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->createMail($connection, $mailTemplateType, $mailTemplate);

        // create flow
        $flowByteId = Uuid::randomBytes();
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED);
        $mailTemplateByteId = $this->getMailTemplateId($connection, $mailTemplateTypeByteId);

        if (!$this->flowExists($connection, OrderPaymentMethodChangedEvent::EVENT_NAME)) {
            $connection->insert(
                'flow',
                [
                    'id' => $flowByteId,
                    'name' => 'Order payment method changed',
                    'event_name' => OrderPaymentMethodChangedEvent::EVENT_NAME,
                    'priority' => 1,
                    'invalid' => 0,
                    'active' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (
            \is_string($mailTemplateByteId)
            && $flowByteId === $this->getFlowId($connection, OrderPaymentMethodChangedEvent::EVENT_NAME)
            && !$this->flowSequenceExists($connection, $flowByteId, 'action.mail.send')
        ) {
            $connection->insert(
                'flow_sequence',
                [
                    'id' => Uuid::randomBytes(),
                    'flow_id' => $flowByteId,
                    'action_name' => 'action.mail.send',
                    'config' => \json_encode([
                        'replyTo' => null,
                        'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateByteId),
                        'documentTypeIds' => [],
                        'recipient' => [
                            'data' => [],
                            'type' => 'default',
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    'display_group' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function getFlowId(Connection $connection, string $eventName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT id FROM flow WHERE event_name = :eventName',
            ['eventName' => $eventName]
        );

        if (!\is_string($result)) {
            return null;
        }

        return $result;
    }

    private function flowExists(Connection $connection, string $eventName): bool
    {
        $result = $connection->fetchOne(
            'SELECT true FROM flow WHERE event_name = :eventName',
            ['eventName' => $eventName]
        );

        return (bool) $result;
    }

    private function flowSequenceExists(Connection $connection, string $flowByteId, string $actionName): bool
    {
        $result = $connection->fetchOne(
            'SELECT true FROM flow_sequence WHERE flow_id = :flowId AND action_name LIKE :actionName',
            ['flowId' => $flowByteId, 'actionName' => $actionName]
        );

        return (bool) $result;
    }
}
