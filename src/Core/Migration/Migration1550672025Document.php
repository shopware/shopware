<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1550672025Document extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550672025;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `document_type` (
  `id` BINARY(16) NOT NULL,
  `technical_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `document_type_translation` (
  `document_type_id` BINARY(16) NOT NULL,
  `language_id` BINARY(16) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `attributes` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`document_type_id`, `language_id`),
  CONSTRAINT `json.document_type_translation.attributes` CHECK (JSON_VALID(`attributes`)),
  CONSTRAINT `fk.document_type_translation.document_type_id` FOREIGN KEY (`document_type_id`)
    REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.document_type_translation.language_id` FOREIGN KEY (`language_id`)
    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `document` (
  `id` BINARY(16) NOT NULL,
  `document_type_id` BINARY(16) NOT NULL,
  `file_type` VARCHAR(255) NOT NULL,
  `order_id` BINARY(16) NOT NULL,
  `order_version_id` BINARY(16) NOT NULL,
  `config` JSON NULL,
  `sent` TINYINT(1) NOT NULL DEFAULT 0,
  `deep_link_code` VARCHAR(32) NOT NULL,
  `attributes` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE `uniq.deep_link_code` (`deep_link_code`),
  CONSTRAINT `json.document.attributes` CHECK (JSON_VALID(`attributes`)),
  CONSTRAINT `json.document.config` CHECK (JSON_VALID(`config`)),
  CONSTRAINT `fk.document.document_type_id` FOREIGN KEY (`document_type_id`)
    REFERENCES `document_type` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk.document.order_id` FOREIGN KEY (`order_id`,`order_version_id`)
    REFERENCES `order` (`id`,`version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $invoiceId = Uuid::randomBytes();
        $deliveryNoteId = Uuid::randomBytes();
        $creditNoteId = Uuid::randomBytes();

        $connection->insert('document_type', ['id' => $invoiceId, 'technical_name' => InvoiceGenerator::INVOICE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type', ['id' => $deliveryNoteId, 'technical_name' => DeliveryNoteGenerator::DELIVERY_NOTE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type', ['id' => $creditNoteId, 'technical_name' => CreditNoteGenerator::CREDIT_NOTE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $invoiceId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE), 'name' => 'Rechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $invoiceId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $deliveryNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE), 'name' => 'Lieferschein', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $deliveryNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Delivery note', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $creditNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE), 'name' => 'Gutschrift', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $creditNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Credit note', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
