<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1562240231UserPasswordRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562240231;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
        $this->registerEventAction($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchColumn();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technical_name' => 'user.recovery.request',
            'available_entities' => json_encode(['userRecovery' => 'user_recovery']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $defaultLangId,
                'name' => 'User password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'User password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $deLangId,
                'name' => 'Benutzer Passwort Wiederherstellung',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'system_default' => true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $defaultLangId,
                'sender_name' => 'Shopware Administration',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => 'Shopware Administration',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $deLangId,
                'sender_name' => 'Shopware Administration',
                'subject' => 'Password Wiederherstellung',
                'description' => '',
                'content_html' => $this->getContentHtmlDe(),
                'content_plain' => $this->getContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function registerEventAction(Connection $connection, string $mailTemplateTypeId): void
    {
        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => 'user.recovery.request',
            'action_name' => 'action.mail.send',
            'config' => json_encode(['mail_template_type_id' => $mailTemplateTypeId]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},<br/>
        <br/>
        there has been a request to reset your password.
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ resetUrl }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},

        there has been a request to reset your password.
        Please confirm the link below to specify a new password.

        Reset password: {{ resetUrl }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
MAIL;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},<br/>
        <br/>
        es wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.<br/>
        <br/>
        <a href="{{ resetUrl }}">Passwort zurücksetzen</a><br/>
        <br/>
        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
    </p>
</div>
MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
        Hallo {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},

        es wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.

        Passwort zurücksetzen: {{ resetUrl }}

        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
MAIL;
    }
}
