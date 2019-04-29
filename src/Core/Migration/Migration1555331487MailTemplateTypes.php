<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1555331487MailTemplateTypes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555331487;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `mail_template_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.technical_name_state_mail_template_type` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
            CREATE TABLE `mail_template_type_translation` (
              `mail_template_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`mail_template_type_id`, `language_id`),
              CONSTRAINT `json.mail_template_type_translation.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.mail_template_type_translation.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_type_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
            ALTER TABLE  `mail_template`
                ADD COLUMN `mail_template_type_id` BINARY(16) NULL AFTER `mail_type`;
SQL;
        $connection->executeQuery($sql);

        $definitionMailTypes = [
            NewsletterSubscriptionServiceInterface::MAIL_TYPE_OPT_IN => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter double opt-in',
                'nameDe' => 'Newsletter Double Opt-In',
            ],
            NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter register',
                'nameDe' => 'Newsletter Registrierung',
            ],
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM => [
                'id' => Uuid::randomHex(),
                'name' => 'Order confirmation',
                'nameDe' => 'Bestellbestätigung',
            ],
            MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer group change accepted',
                'nameDe' => 'Kundengruppenwechsel akzeptiert',
            ],
            MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer group change rejected',
                'nameDe' => 'Kundengruppenwechsel abgelehnt',
            ],
            MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE => [
                'id' => Uuid::randomHex(),
                'name' => 'Password changed',
                'nameDe' => 'Passwort geändert',
            ],
            MailTemplateTypes::MAILTYPE_SEPA_CONFIRMATION => [
                'id' => Uuid::randomHex(),
                'name' => 'Sepa authorization',
                'nameDe' => 'Sepa Authorisierung',
            ],
            MailTemplateTypes::MAILTYPE_STOCK_WARNING => [
                'id' => Uuid::randomHex(),
                'name' => 'Product stock warning',
                'nameDe' => 'Lagerbestandshinweis',
            ],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Open',
                'nameDe' => 'Eintritt Bestellstatus: Offen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Shipped (partially)',
                'nameDe' => 'Eintritt Bestellstatus: Teilweise versandt',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Returned',
                'nameDe' => 'Eintritt Bestellstatus: Retour',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Shipped',
                'nameDe' => 'Eintritt Bestellstatus: Versandt',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Cancelled',
                'nameDe' => 'Eintritt Bestellstatus: Abgebrochen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Reminded',
                'nameDe' => 'Eintritt Zahlungsstatus: Erinnert',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Refunded (partially)',
                'nameDe' => 'Eintritt Zahlungsstatus: Teilweise erstattet',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Cancelled',
                'nameDe' => 'Eintritt Zahlungsstatus: Abgebrochen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Paid',
                'nameDe' => 'Eintritt Zahlungsstatus: Bezahlt',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Refunded',
                'nameDe' => 'Eintritt Zahlungsstatus: Erstattet',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Paid (partially)',
                'nameDe' => 'Eintritt Zahlungsstatus: Teilweise bezahlt',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Open',
                'nameDe' => 'Eintritt Zahlungsstatus: Offen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Open',
                'nameDe' => 'Eintritt Bestellstatus: Offen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: In progress',
                'nameDe' => 'Eintritt Bestellstatus: In Bearbeitung',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Cancelled',
                'nameDe' => 'Eintritt Bestellstatus: Abgebrochen',
            ],

            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Done',
                'nameDe' => 'Eintritt Bestellstatus: Abgeschlossen',
            ],
        ];

        $this->createMailType($connection, $definitionMailTypes);

        $sql = <<<SQL
            ALTER TABLE  `mail_template`
                DROP COLUMN `mail_type`,
                ADD CONSTRAINT `fk.mail_template_type.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SQL;
        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function createMailType(Connection $connection, $definitionMailTypes)
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($definitionMailTypes as $typeName => $mailType) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => Uuid::fromHexToBytes($mailType['id']),
                    'technical_name' => $typeName,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['name'],
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );

            $sql = <<<SQL
            UPDATE `mail_template`
                SET `mail_template_type_id` = UNHEX(:type_id)
            WHERE `mail_type` = :technical_name;
SQL;
            $connection->executeQuery($sql, ['type_id' => $mailType['id'], 'technical_name' => $typeName]);
        }
    }
}
