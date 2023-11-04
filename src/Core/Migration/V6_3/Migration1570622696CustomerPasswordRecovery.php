<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1570622696CustomerPasswordRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570622696;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `customer_recovery` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `hash` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.customer_recovery.customer_id` UNIQUE (`customer_id`),
                CONSTRAINT `fk.customer_recovery.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($query);

        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
        $this->registerEventAction($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
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
            'technical_name' => 'customer.recovery.request',
            'available_entities' => json_encode(['customerRecovery' => 'customer_recovery']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $defaultLangId,
                'name' => 'Customer password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Customer password recovery',
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
                'sender_name' => '{{ shopName }}',
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
                'sender_name' => '{{ shopName }}',
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
                'language_id' => $this->getLanguageIdByLocale($connection, 'de-DE'),
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password Wiederherstellung',
                'description' => '',
                'content_html' => $this->getContentHtmlDe(),
                'content_plain' => $this->getContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $this->addTemplateToSalesChannels($connection, $mailTemplateTypeId, $mailTemplateId);
    }

    private function addTemplateToSalesChannels(Connection $connection, string $mailTemplateTypeId, string $mailTemplateId): void
    {
        $salesChannels = $connection->fetchAllAssociative('SELECT `id` FROM `sales_channel` ');

        foreach ($salesChannels as $salesChannel) {
            $mailTemplateSalesChannel = [
                'id' => Uuid::randomBytes(),
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'sales_channel_id' => $salesChannel['id'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $connection->insert('mail_template_sales_channel', $mailTemplateSalesChannel);
        }
    }

    private function registerEventAction(Connection $connection, string $mailTemplateTypeId): void
    {
        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => 'customer.recovery.request',
            'action_name' => 'action.mail.send',
            'config' => json_encode(['mail_template_type_id' => $mailTemplateTypeId], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.<br/>
        <br/>
        Yours sincerely
        Your {{ shopName }} team
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:

        {{ resetUrl }}

        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.

        Yours sincerely
        Your {{ shopName }}-Team
MAIL;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        Sie haben ein neues Passwort für Ihren {{ shopName }}-Account angefordert.
        Klicken Sie auf folgenden Link, um Ihr Passwort zurückzusetzen:<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        Dieser Link ist für die nächsten 2 Stunden gültig.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.<br/>
        <br/>
        Mit freundlichen Grüßen
        Ihr {{ shopName }}-Team
    </p>
</div>
MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
        Hallo {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        Sie haben ein neues Passwort für Ihren {{ shopName }}-Account angefordert.
        Klicken Sie auf folgenden Link, um Ihr Passwort zurückzusetzen:

        {{ resetUrl }}

        Dieser Link ist für die nächsten 2 Stunden gültig.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.

        Mit freundlichen Grüßen
        Ihr {{ shopName }}-Team
MAIL;
    }
}
