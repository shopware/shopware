<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675218708;
    }

    public function update(Connection $connection): void
    {
        $flowTemplate = $connection->fetchAssociative(
            'SELECT `id`, `config` FROM `flow_template` WHERE `name` = :name',
            ['name' => 'Deliver ordered product downloads']
        );

        if (!$flowTemplate) {
            return;
        }

        $mailTemplateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]);
        $mailTemplateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $mailTemplateTypeId]);

        if ($mailTemplateId === false) {
            $mailTemplateId = null;
        }

        $flowTemplateConfig = json_decode((string) $flowTemplate['config'], true);
        $ruleIds = array_filter(array_column($flowTemplateConfig['sequences'], 'ruleId'));
        $ruleId = !empty($ruleIds) ? $ruleIds[0] : null;

        $ruleSequenceId = Uuid::randomHex();
        $sequenceConfig = [
            [
                'id' => $ruleSequenceId,
                'ruleId' => $ruleId,
                'parentId' => null,
                'actionName' => null,
                'position' => 1,
                'trueCase' => 0,
                'config' => [],
                'displayGroup' => 1,
            ],
            [
                'id' => Uuid::randomHex(),
                'ruleId' => null,
                'parentId' => $ruleSequenceId,
                'actionName' => 'action.grant.download.access',
                'position' => 1,
                'trueCase' => 1,
                'config' => ['value' => true],
                'displayGroup' => 1,
            ],
        ];

        if ($mailTemplateId !== null) {
            $sequenceConfig[] = [
                'id' => Uuid::randomHex(),
                'actionName' => 'action.mail.send',
                'config' => [
                    'recipient' => [
                        'data' => [],
                        'type' => 'default',
                    ],
                    'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateId),
                    'mailTemplateTypeId' => Uuid::fromBytesToHex($mailTemplateTypeId),
                    'documentTypeIds' => [],
                ],
                'parentId' => $ruleSequenceId,
                'ruleId' => null,
                'position' => 2,
                'trueCase' => 1,
                'displayGroup' => 1,
            ];
        }

        $connection->update(
            FlowTemplateDefinition::ENTITY_NAME,
            [
                'config' => json_encode([
                    'eventName' => 'state_enter.order_transaction.state.paid',
                    'description' => null,
                    'customFields' => null,
                    'sequences' => $sequenceConfig,
                ], \JSON_THROW_ON_ERROR),
            ],
            [
                'id' => $flowTemplate['id'],
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
