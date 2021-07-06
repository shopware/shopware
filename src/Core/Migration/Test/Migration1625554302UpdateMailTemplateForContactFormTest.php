<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1625554302UpdateMailTemplateForContactForm;

class Migration1625554302UpdateMailTemplateForContactFormTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEnContactFormTemplateIsUpdated(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        /** @var string $enLangId */
        $enLangId = $this->fetchEnLanguageId($connection);

        static::assertNotNull($enLangId);

        $migration = new Migration1625554302UpdateMailTemplateForContactForm();
        $migration->update($connection);

        /** @var string $contactFormTemplateId */
        $contactFormTemplateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_CONTACT_FORM);
        static::assertNotNull($contactFormTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $contactFormTemplateTranslation = $connection->fetchAssoc($sqlString, [
            'langId' => $enLangId,
            'templateId' => $contactFormTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($contactFormTemplateTranslation)) {
            static::assertEquals('Contact form received - {{ salesChannel.name }}', $contactFormTemplateTranslation['subject']);
            static::assertEquals($this->getContactFormHtmlTemplateEn(), $contactFormTemplateTranslation['content_html']);
            static::assertEquals($this->getContactFormPlainTemplateEn(), $contactFormTemplateTranslation['content_plain']);
        }

        $deLangId = $this->fetchDeLanguageId($connection);

        $contactFormTemplateDeTranslation = $connection->fetchAssoc($sqlString, [
            'langId' => $deLangId,
            'templateId' => $contactFormTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($contactFormTemplateDeTranslation)) {
            static::assertEquals('Kontaktanfrage erhalten - {{ salesChannel.name }}', $contactFormTemplateDeTranslation['subject']);
            static::assertEquals($this->getContactFormHtmlTemplateDe(), $contactFormTemplateDeTranslation['content_html']);
            static::assertEquals($this->getContactFormPlainTemplateDe(), $contactFormTemplateDeTranslation['content_plain']);
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchColumn();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchColumn();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
    }

    private function fetchDeLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'de-DE']) ?: null;
    }

    private function getContactFormHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        The following Message was sent to you via the contact form.<br/>
        <br/>
        Contact name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
        <br/>
        Contact email address: {{ contactFormData.email }}
        <br/>
        Phone: {{ contactFormData.phone }}<br/>
        <br/>
        Subject: {{ contactFormData.subject }}<br/>
        <br/>
        Message: {{ contactFormData.comment }}<br/>
    </p>
</div>
';
    }

    private function getContactFormPlainTemplateEn(): string
    {
        return 'The following Message was sent to you via the contact form.

Contact name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
Contact email address: {{ contactFormData.email }}
Phone: {{ contactFormData.phone }}

Subject: {{ contactFormData.subject }}

Message: {{ contactFormData.comment }}
';
    }

    private function getContactFormHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Folgende Nachricht wurde an Sie via Kontakt-Formular gesendet.<br/>
        <br/>
        Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
        <br/>
        Kontakt E-Mail: {{ contactFormData.email }}<br/>
        <br>
        Telefonnummer: {{ contactFormData.phone }}<br/>
        <br/>
        Betreff: {{ contactFormData.subject }}<br/>
        <br/>
        Message: {{ contactFormData.comment }}<br/>
    </p>
</div>
';
    }

    private function getContactFormPlainTemplateDe(): string
    {
        return 'Folgende Nachricht wurde an Sie via Kontakt-Formular gesendet.

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
Kontakt E-Mail: {{ contactFormData.email }}

Telefonnummer: {{ contactFormData.phone }}

Betreff: {{ contactFormData.subject }}

Nachricht: {{ contactFormData.comment }}
';
    }
}
