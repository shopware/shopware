<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1580808849AddGermanContactFormTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580808849;
    }

    public function update(Connection $connection): void
    {
        $deLangId = $this->getGermanLanguageId($connection);

        if ($deLangId === null) {
            return;
        }

        $contactTemplateId = $this->getContactMailTemplateId($connection);

        if (!$contactTemplateId) {
            return;
        }

        $germanTranslation = $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mail_template_id AND `language_id` = :language_id LIMIT 1',
            [
                'mail_template_id' => $contactTemplateId,
                'language_id' => $deLangId,
            ]
        );

        if ($germanTranslation) {
            return;
        }

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $contactTemplateId,
                'language_id' => $deLangId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Kontaktanfrage erhalten - {{ salesChannel.name }}',
                'description' => 'Kontaktanfrage erhalten',
                'content_html' => $this->getContactFormHtmlTemplateDe(),
                'content_plain' => $this->getContactFormPlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function getGermanLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne('
            SELECT `id` FROM `language` WHERE LOWER(`name`) = \'deutsch\'
        ');

        return $result === false ? null : (string) $result;
    }

    private function getContactMailTemplateId(Connection $connection): ?string
    {
        $sql = <<<'SQL'
    SELECT `mail_template`.`id`
    FROM `mail_template` LEFT JOIN `mail_template_type` ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
    WHERE `mail_template_type`.`technical_name` = :technical_name
    AND `system_default` = 1
SQL;

        $result = $connection->executeQuery(
            $sql,
            ['technical_name' => MailTemplateTypes::MAILTYPE_CONTACT_FORM]
        )->fetchOne();

        return $result === false ? null : (string) $result;
    }

    private function getContactFormHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Folgende Nachricht wurde von {{ contactFormData.firstName }} {{ contactFormData.lastName }} an Sie via Kontakt-Formular gesendet.<br/>
        <br/>
        Kontakt E-Mail: {{ contactFormData.email }}<br/>
        <br>
        Telefonnummer: {{ contactFormData.phone }}<br/>
        <br/>
        Betreff: {{ contactFormData.subject }}<br/>
        <br/>
        Message: {{ contactFormData.comment }}<br/>
    </p>
</div>';
    }

    private function getContactFormPlainTemplateDe(): string
    {
        return 'Folgende Nachricht wurde von {{ contactFormData.firstName }} {{ contactFormData.lastName }} an Sie via Kontakt-Formular gesendet.

Kontakt E-Mail: {{ contactFormData.email }}

Telefonnummer: {{ contactFormData.phone }}

Betreff: {{ contactFormData.subject }}

Nachricht: {{ contactFormData.comment }}';
    }
}
