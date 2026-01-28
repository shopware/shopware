<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1763377575SendEmailAfterPasswordChangeFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763377575;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $flowId = $this->createFlow($connection);
            $mailTemplateTypeId = $this->getMailTemplateTypeId($connection);
            $mailTemplateId = $this->getMailTemplateId($connection, $mailTemplateTypeId);
            $flowSequenceId = $this->createFlowSequence($connection, $flowId, $mailTemplateId);
            $this->createFlowTemplate($connection, $flowSequenceId, $mailTemplateId, $mailTemplateTypeId);

            $this->registerIndexer($connection, 'flow.indexer');

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private function createFlow(Connection $connection): string
    {
        $createdFlow = $connection->fetchOne(
            'SELECT `id` FROM `flow` WHERE `event_name` = :name',
            ['name' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        if ($createdFlow) {
            return $createdFlow;
        }

        $flowId = Uuid::randomBytes();

        $connection->insert(
            'flow',
            [
                'id' => $flowId,
                'name' => 'Customer password changed',
                'event_name' => CustomerPasswordChangedEvent::EVENT_NAME,
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $flowId;
    }

    private function createFlowSequence(Connection $connection, string $flowId, string $mailTemplateId): string
    {
        $createdFlowSequence = $connection->fetchOne(
            'SELECT `id` FROM `flow_sequence` WHERE `flow_id` = :id',
            ['id' => $flowId]
        );

        if ($createdFlowSequence) {
            return $createdFlowSequence;
        }

        $flowSequenceId = Uuid::randomBytes();

        $connection->insert(
            'flow_sequence',
            [
                'id' => $flowSequenceId,
                'flow_id' => $flowId,
                'rule_id' => null,
                'parent_id' => null,
                'action_name' => SendMailAction::ACTION_NAME,
                'position' => 1,
                'true_case' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => \sprintf(
                    '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                    Uuid::fromBytesToHex($mailTemplateId)
                ),
            ]
        );

        return $flowSequenceId;
    }

    private function createFlowTemplate(Connection $connection, string $flowSequenceId, string $mailTemplateId, string $mailTemplateTypeId): void
    {
        $createdFlowTemplate = $connection->fetchOne(
            'SELECT `id` FROM `flow_template` WHERE JSON_EXTRACT(config, \'$.eventName\') = :eventName',
            ['eventName' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        if ($createdFlowTemplate) {
            return;
        }

        $connection->insert(
            'flow_template',
            [
                'id' => Uuid::randomBytes(),
                'name' => 'Customer password changed',
                'config' => \sprintf(
                    '{"eventName": "%s", "sequences": [{"id": "%s", "config": {"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "mailTemplateTypeId": "%s"}, "ruleId": null, "parentId": null, "position": 1, "trueCase": 0, "actionName": "action.mail.send", "displayGroup": 1}], "description": null, "customFields": null}',
                    CustomerPasswordChangedEvent::EVENT_NAME,
                    Uuid::fromBytesToHex($flowSequenceId),
                    Uuid::fromBytesToHex($mailTemplateId),
                    Uuid::fromBytesToHex($mailTemplateTypeId)
                ),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getMailTemplateId(Connection $connection, string $mailTemplateTypeId): string
    {
        return $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :id',
            ['id' => $mailTemplateTypeId]
        );
    }

    private function getMailTemplateTypeId(Connection $connection): string
    {
        return $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name',
            ['name' => CustomerPasswordChangedEvent::EVENT_NAME]
        );
    }
}
