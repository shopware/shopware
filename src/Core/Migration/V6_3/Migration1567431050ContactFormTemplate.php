<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1567431050ContactFormTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1567431050;
    }

    public function update(Connection $connection): void
    {
        $contactTemplateId = $this->getContactMailTemplateId($connection);
        $contactEventConfig = $this->getContactMailEventConfig($connection);

        $config = json_decode($contactEventConfig, true);
        $contactTemplateTypeId = Uuid::fromHexToBytes($config['mail_template_type_id']);

        $update = false;
        if (!$contactTemplateId) {
            $contactTemplateId = Uuid::randomBytes();
        } else {
            $update = true;
        }

        if (!\is_string($contactTemplateId)) {
            return;
        }

        if ($update === true) {
            $connection->update(
                'mail_template',
                [
                    'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                ['id' => $contactTemplateId]
            );

            $connection->delete('mail_template_translation', ['mail_template_id' => $contactTemplateId]);
        } else {
            $connection->insert(
                'mail_template',
                [
                    'id' => $contactTemplateId,
                    'mail_template_type_id' => $contactTemplateTypeId,
                    'system_default' => 1,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Contact form received - {{ salesChannel.name }}',
                'description' => 'Contact form received',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateEn(),
                'content_plain' => $this->getRegistrationPlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $contactTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getContactMailEventConfig(Connection $connection): string
    {
        $sql = <<<'SQL'
SELECT `event_action`.`config`
FROM `event_action`
WHERE `event_action`.`event_name` = :event_name AND `event_action`.`action_name` = :action_name
SQL;

        $contactEventConfig = (string) $connection->executeQuery(
            $sql,
            [
                'event_name' => ContactFormEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
            ]
        )->fetchColumn();

        return $contactEventConfig;
    }

    private function getContactMailTemplateId(Connection $connection): ?string
    {
        $sql = <<<'SQL'
    SELECT `mail_template`.`id`
    FROM `mail_template` LEFT JOIN `mail_template_type` ON `mail_template`.`mail_template_type_id` = `mail_template_type`.id
    WHERE `mail_template_type`.`technical_name` = :technical_name
SQL;

        $templateTypeId = $connection->executeQuery(
            $sql,
            [
                'technical_name' => MailTemplateTypes::MAILTYPE_CONTACT_FORM,
            ]
        )->fetchColumn();

        if ($templateTypeId) {
            return $templateTypeId;
        }

        return null;
    }

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Following Message was sent to you by {{ contactFormData.firstName }} {{ contactFormData.lastName }} via the contact form.<br/>
                <br/>
                Contact email address: {{ contactFormData.email }}<br/>
                Phone: {{ contactFormData.phone }}<br/><br/>
                Subject: {{ contactFormData.subject }}<br/>
                <br/>
                Message: {{ contactFormData.comment }}<br/>
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return 'Following Message was sent to you by {{ contactFormData.firstName }} {{ contactFormData.lastName }} via the contact form.

                Contact email address: {{ contactFormData.email }}
                Phone: {{ contactFormData.phone }}

                Subject: {{ contactFormData.subject }}

                Message: {{ contactFormData.comment }}';
    }
}
