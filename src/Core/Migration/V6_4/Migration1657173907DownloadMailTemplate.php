<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1657173907DownloadMailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1657173907;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $this->insertMailTemplateTypeData($connection);
        $this->insertMailTemplateData($templateTypeId, $connection);
        $this->updateMailTemplateContent($connection);
        $this->updateExistingMailTemplateContent($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function updateMailTemplateContent(Connection $connection): void
    {
        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/downloads_delivery/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/downloads_delivery/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/downloads_delivery/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/downloads_delivery/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    private function updateExistingMailTemplateContent(Connection $connection): void
    {
        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        try {
            $result = $connection->fetchOne(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $languageName]
            );

            if ($result === false) {
                return null;
            }

            return (string) $result;
        } catch (\Throwable) {
            return null;
        }
    }

    private function insertMailTemplateTypeData(Connection $connection): string
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]);

        if ($templateTypeId) {
            return $templateTypeId;
        }

        $templateTypeId = Uuid::randomBytes();
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY,
                'available_entities' => '{"order":"order","salesChannel":"sales_channel"}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => 'Delivery of digital products',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $englishLanguageId,
                    'name' => 'Delivery of digital products',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $germanLanguageId,
                    'name' => 'Versand digitaler Produkte',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $templateTypeId;
    }

    private function insertMailTemplateData(string $templateTypeId, Connection $connection): void
    {
        $templateId = Uuid::randomBytes();
        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Your downloads from {{ salesChannel.name }} are ready',
                    'description' => 'Shopware Default Template',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $defaultLanguageId,
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Your downloads from {{ salesChannel.name }} are ready',
                    'description' => 'Shopware Default Template',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $englishLanguageId,
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Ihre Dateien von {{ salesChannel.name }} stehen bereit',
                    'description' => 'Shopware Default Template',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $germanLanguageId,
                ]
            );
        }
    }
}
