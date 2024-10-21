<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('services-settings')]
class Migration1689257577AddMissingTransactionMailFlow extends MigrationStep
{
    public const AUTHORIZED_FLOW = 'Order enters status authorized';

    public const CHARGEBACK_FLOW = 'Order enters status chargeback';

    public const UNCONFIRMED_FLOW = 'Order enters status unconfirmed';

    public function getCreationTimestamp(): int
    {
        return 1689257577;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $mailTemplates = $this->getMailTemplates($connection);

        foreach ($mailTemplates as $mailTemplate) {
            if (!\is_string($mailTemplate['mailTemplateId'])) {
                $mailTemplate['mailTemplateId'] = null;
            }

            $this->insertFlowData($connection, $mailTemplate);
            $this->insertFlowTemplateData($connection, $mailTemplate);
        }

        $this->registerIndexer($connection, 'flow.indexer');
    }

    /**
     * @param array{mailTemplateId: string|null, flowName: string, event: string} $mailTemplate
     *
     * @throws Exception
     */
    private function insertFlowData(Connection $connection, array $mailTemplate): void
    {
        $flowId = $connection->fetchOne('SELECT id FROM flow WHERE name = :name', ['name' => $mailTemplate['flowName']]);

        if ($flowId) {
            return;
        }

        $flowId = Uuid::randomBytes();

        $connection->insert(
            'flow',
            [
                'id' => $flowId,
                'name' => $mailTemplate['flowName'],
                'event_name' => $mailTemplate['event'],
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if ($mailTemplate['mailTemplateId'] !== null) {
            $connection->insert(
                'flow_sequence',
                [
                    'id' => Uuid::randomBytes(),
                    'flow_id' => $flowId,
                    'rule_id' => null,
                    'parent_id' => null,
                    'action_name' => SendMailAction::ACTION_NAME,
                    'position' => 1,
                    'true_case' => 0,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'config' => \sprintf(
                        '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                        Uuid::fromBytesToHex($mailTemplate['mailTemplateId'])
                    ),
                ]
            );
        }
    }

    /**
     * @param array{mailTemplateId: string|null, flowName: string, event: string} $mailTemplate
     *
     * @throws Exception
     */
    private function insertFlowTemplateData(Connection $connection, array $mailTemplate): void
    {
        $flowTemplateId = $connection->fetchOne('SELECT id FROM flow_template WHERE name = :name', ['name' => $mailTemplate['flowName']]);

        if ($flowTemplateId) {
            return;
        }

        $sequenceConfig = [];

        if ($mailTemplate['mailTemplateId'] !== null) {
            $sequenceConfig[] = [
                'id' => Uuid::randomHex(),
                'actionName' => SendMailAction::ACTION_NAME,
                'config' => \sprintf(
                    '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                    Uuid::fromBytesToHex($mailTemplate['mailTemplateId'])
                ),
                'parentId' => null,
                'ruleId' => null,
                'position' => 1,
                'trueCase' => 0,
                'displayGroup' => 1,
            ];
        }

        $connection->insert(FlowTemplateDefinition::ENTITY_NAME, [
            'id' => Uuid::randomBytes(),
            'name' => $mailTemplate['flowName'],
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'eventName' => $mailTemplate['event'],
                'description' => null,
                'customFields' => null,
                'sequences' => $sequenceConfig,
            ]),
        ]);
    }

    /**
     * @throws Exception
     *
     * @return array<string, array{mailTemplateId: string|bool, flowName: string, event: string}>
     */
    private function getMailTemplates(Connection $connection): array
    {
        $getTemplateId = function (string $flowName) use ($connection): string|bool {
            $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => $flowName]);

            return $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);
        };

        $AUTHORIZED_TYPE = Migration1688106315AddMissingTransactionMailTemplates::AUTHORIZED_TYPE;
        $CHARGEBACK_TYPE = Migration1688106315AddMissingTransactionMailTemplates::CHARGEBACK_TYPE;
        $UNCONFIRMED_TYPE = Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE;

        return [
            $AUTHORIZED_TYPE => [
                'mailTemplateId' => $getTemplateId($AUTHORIZED_TYPE),
                'flowName' => self::AUTHORIZED_FLOW,
                'event' => 'state_enter.order_transaction.state.authorized',
            ],
            $CHARGEBACK_TYPE => [
                'mailTemplateId' => $getTemplateId($CHARGEBACK_TYPE),
                'flowName' => self::CHARGEBACK_FLOW,
                'event' => 'state_enter.order_transaction.state.chargeback',
            ],
            $UNCONFIRMED_TYPE => [
                'mailTemplateId' => $getTemplateId($UNCONFIRMED_TYPE),
                'flowName' => self::UNCONFIRMED_FLOW,
                'event' => 'state_enter.order_transaction.state.unconfirmed',
            ],
        ];
    }
}
