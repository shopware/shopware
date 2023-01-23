<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1659257396DownloadFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659257396;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]);
        $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);

        if ($templateId === false) {
            $templateId = null;
        }

        $ruleId = $this->createRule($connection);
        $this->createFlow($connection, $ruleId, $templateId);
        $this->createFlowTemplate($connection, $ruleId, $templateId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function createRule(Connection $connection): string
    {
        $ruleId = $connection->fetchOne('SELECT id FROM rule WHERE name = :name', ['name' => 'Shopping cart / Order with digital products']);

        if ($ruleId) {
            return $ruleId;
        }

        $idRule = Uuid::randomBytes();
        $idCondition = Uuid::randomBytes();

        $connection->insert(
            'rule',
            [
                'id' => $idRule,
                'name' => 'Shopping cart / Order with digital products',
                'description' => null,
                'priority' => 1,
                'invalid' => 0,
                'module_types' => null,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ]
        );
        $connection->insert(
            'rule_condition',
            [
                'id' => $idCondition,
                'type' => 'andContainer',
                'rule_id' => $idRule,
                'parent_id' => null,
                'value' => '[]',
                'position' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ]
        );
        $connection->insert(
            'rule_condition',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'cartLineItemProductStates',
                'rule_id' => $idRule,
                'parent_id' => $idCondition,
                'value' => sprintf('{"operator": "=", "productState": "%s"}', State::IS_DOWNLOAD),
                'position' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ]
        );

        $this->registerIndexer($connection, 'Swag.RulePayloadIndexer');

        return $idRule;
    }

    private function createFlow(Connection $connection, string $ruleId, ?string $mailTemplateId): void
    {
        $flowId = $connection->fetchOne('SELECT id FROM flow WHERE name = :name', ['name' => 'Deliver ordered product downloads']);

        if ($flowId) {
            return;
        }

        $flowId = Uuid::randomBytes();

        $connection->insert(
            'flow',
            [
                'id' => $flowId,
                'name' => 'Deliver ordered product downloads',
                'event_name' => 'state_enter.order_transaction.state.paid',
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $ruleSequenceId = Uuid::randomBytes();

        $connection->insert(
            'flow_sequence',
            [
                'id' => $ruleSequenceId,
                'flow_id' => $flowId,
                'rule_id' => $ruleId,
                'parent_id' => null,
                'action_name' => null,
                'position' => 1,
                'true_case' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => '[]',
            ]
        );

        $connection->insert(
            'flow_sequence',
            [
                'id' => Uuid::randomBytes(),
                'flow_id' => $flowId,
                'rule_id' => null,
                'parent_id' => $ruleSequenceId,
                'action_name' => 'action.grant.download.access',
                'position' => 1,
                'true_case' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => '{"value": true}',
            ]
        );

        if ($mailTemplateId !== null) {
            $connection->insert(
                'flow_sequence',
                [
                    'id' => Uuid::randomBytes(),
                    'flow_id' => $flowId,
                    'rule_id' => null,
                    'parent_id' => $ruleSequenceId,
                    'action_name' => 'action.mail.send',
                    'position' => 2,
                    'true_case' => 1,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'config' => sprintf(
                        '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                        Uuid::fromBytesToHex($mailTemplateId)
                    ),
                ]
            );
        }

        $this->registerIndexer($connection, 'flow.indexer');
    }

    private function createFlowTemplate(Connection $connection, string $ruleId, ?string $mailTemplateId): void
    {
        $flowTemplateId = $connection->fetchOne('SELECT id FROM flow_template WHERE name = :name', ['name' => 'Deliver ordered product downloads']);

        if ($flowTemplateId) {
            return;
        }

        $ruleSequenceId = Uuid::randomHex();
        $sequenceConfig = [
            [
                'id' => $ruleSequenceId,
                'ruleId' => Uuid::fromBytesToHex($ruleId),
                'parentId' => null,
                'actionName' => null,
                'position' => 1,
                'trueCase' => 0,
                'config' => '[]',
            ],
            [
                'id' => Uuid::randomHex(),
                'ruleId' => null,
                'parentId' => $ruleSequenceId,
                'actionName' => 'action.grant.download.access',
                'position' => 1,
                'trueCase' => 1,
                'config' => '{"value": true}',
            ],
        ];

        if ($mailTemplateId !== null) {
            $sequenceConfig[] = [
                'id' => Uuid::randomHex(),
                'actionName' => 'action.mail.send',
                'config' => sprintf(
                    '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                    Uuid::fromBytesToHex($mailTemplateId)
                ),
                'parentId' => $ruleSequenceId,
                'ruleId' => null,
                'position' => 1,
                'trueCase' => 0,
                'displayGroup' => 1,
            ];
        }

        $connection->insert(FlowTemplateDefinition::ENTITY_NAME, [
            'id' => Uuid::randomBytes(),
            'name' => 'Deliver ordered product downloads',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'eventName' => 'state_enter.order_transaction.state.paid',
                'description' => null,
                'customFields' => null,
                'sequences' => $sequenceConfig,
            ]),
        ]);
    }
}
