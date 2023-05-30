<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1672934282ReviewFormSendFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1672934282;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => MailTemplateTypes::MAILTYPE_REVIEW_FORM]);
        $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);
        if (!$templateId) {
            $templateId = Uuid::randomBytes();
        }

        $this->createFlow($connection, $templateId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createFlow(Connection $connection, string $mailtTemplateId): void
    {
        $flowId = Uuid::randomBytes();

        $connection->insert(
            'flow',
            [
                'id' => $flowId,
                'name' => 'Review form sent',
                'event_name' => 'review_form.send',
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'flow_sequence',
            [
                'id' => Uuid::randomBytes(),
                'flow_id' => $flowId,
                'rule_id' => null,
                'parent_id' => null,
                'action_name' => 'action.mail.send',
                'position' => 1,
                'true_case' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => sprintf(
                    '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                    Uuid::fromBytesToHex($mailtTemplateId)
                ),
            ]
        );

        $this->registerIndexer($connection, 'flow.indexer');
    }
}
