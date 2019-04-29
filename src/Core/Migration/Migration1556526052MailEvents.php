<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1556526052MailEvents extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556526052;
    }

    public function update(Connection $connection): void
    {
        //toDo name translateable
        $sql = <<<SQL
    CREATE TABLE `business_action` (
      `id` BINARY(16) NOT NULL,
      `name` VARCHAR(64) NOT NULL, 
      `technical_name` VARCHAR(64) DEFAULT '',
      `need_available_data` JSON NULL,
      `created_at` DATETIME(3) NOT NULL,
      `updated_at` DATETIME(3) NOT NULL,
      PRIMARY KEY (`id`),
      KEY `idx.business_action.technicalName` (`technical_name`),
      CONSTRAINT `json.business_action.need_available_data` CHECK (JSON_VALID(`need_available_data`))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
    CREATE TABLE `mail_template_business_action` (
                `mail_template_id` binary(16) NOT NULL,
                `business_action_id` binary(16) NOT NULL,
                `created_at` datetime(3) NOT NULL,
                CONSTRAINT `fk.mail_template_business_action.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.mail_template_business_action.business_action_id` FOREIGN KEY (`business_action_id`) REFERENCES `business_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $orderDoneId = Uuid::randomBytes();
        $customerRegisteredId = Uuid::randomBytes();

        $connection->insert(
            'business_action',
            [
                'id' => $orderDoneId,
                'name' => 'Send Order Done Mail',
                'technical_name' => 'action.mail.send.order.done',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'updated_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'business_action',
            [
                'id' => $customerRegisteredId,
                'name' => 'Send Customer Registration Mail',
                'technical_name' => 'action.mail.send.customer.registered',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'updated_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $registeredMailId = $connection->executeQuery(
            'SELECT id FROM mail_template WHERE mail_type = :mail_type',
            ['mail_type' => NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER]
        )->fetchAll(FetchMode::COLUMN);

        $registeredMailId = $registeredMailId[0];

        $orderDoneMailId = Uuid::randomBytes();
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        $connection->insert(
            'mail_template',
            [
                'id' => $orderDoneMailId,
                'sender_mail' => 'info@shopware.com',
                'mail_type' => 'orderDone',
                'system_default' => true,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $orderDoneMailId,
                'language_id' => $languageEn,
                'subject' => 'Your Order',
                'description' => '',
                'content_html' => 'your Order',
                'content_plain' => 'your Order',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $orderDoneMailId,
                'language_id' => $languageDe,
                'subject' => 'Newsletter',
                'description' => 'Deine Bestellung',
                'content_html' => 'Deine Bestellung',
                'content_plain' => 'Deine Bestellung',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => 'checkout.customer.register',
                'action_name' => 'action.mail.send.customer.registered',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'updated_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => 'checkout.order.done',
                'action_name' => 'action.mail.send.order.done',
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'updated_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_business_action',
            [
                'mail_template_id' => $registeredMailId,
                'business_action_id' => $customerRegisteredId,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_business_action',
            [
                'mail_template_id' => $orderDoneMailId,
                'business_action_id' => $orderDoneId,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
