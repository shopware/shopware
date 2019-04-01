<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1552314950DocumentConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552314950;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `document_base_config` (
  `id` BINARY(16) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `filename_prefix` VARCHAR(64) DEFAULT '',
  `filename_suffix` VARCHAR(64) DEFAULT '',
  `document_number` VARCHAR(64) DEFAULT '',
  `global` TINYINT(1) DEFAULT 0,
  `type_id` BINARY(16) NOT NULL,
  `logo_id` BINARY(16) NULL,
  `config` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.document_base_config.type_id` (`type_id`),
  CONSTRAINT `json.config` CHECK (JSON_VALID(`config`)),
  CONSTRAINT `fk.document_base_config.type_id` FOREIGN KEY (`type_id`) REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.document_base_config.logo_id` FOREIGN KEY (`logo_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
            CREATE TABLE `document_base_config_sales_channel` (
              `id` BINARY(16) NOT NULL,
              `document_base_config_id` BINARY(16) NOT NULL,
              `document_type_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              UNIQUE `uniq.document_base_configuration_id__sales_channel_id` (`document_type_id`, `sales_channel_id`),
              CONSTRAINT `fk.document_base_config_sales_channel.document_base_config_id`
                FOREIGN KEY (document_base_config_id) REFERENCES `document_base_config` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.document_base_config_sales_channel.document_type_id`
                FOREIGN KEY (document_type_id) REFERENCES `document_type` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.document_base_config_sales_channel.sales_channel_id`
                FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $stornoId = Uuid::randomBytes();

        $connection->insert('document_type', ['id' => $stornoId, 'technical_name' => DocumentTypes::STORNO, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE), 'name' => 'Stornorechnung', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Storno bill', 'created_at' => date(Defaults::DATE_FORMAT)]);

        $stornoConfigId = Uuid::randomBytes();
        $invoiceConfigId = Uuid::randomBytes();
        $deliveryConfigId = Uuid::randomBytes();
        $creditConfigId = Uuid::randomBytes();

        $invoiceId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => DocumentTypes::INVOICE]);
        $deliverNoteId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => DocumentTypes::DELIVERY_NOTE]);
        $creditNoteId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => DocumentTypes::CREDIT_NOTE]);

        $defaultConfig = [
            'displayPrices' => true,
            'displayFooter' => true,
            'displayHeader' => true,
            'displayLineItems' => true,
            'diplayLineItemPosition' => true,
            'displayPageCount' => true,
            'displayCompanyAddress' => true,
            'itemsPerPage' => 10,
            'companyName' => 'Muster AG',
            'taxNumber' => '000111000',
            'vatId' => 'XX 111 222 333',
            'taxOffice' => 'Coesfeld',
            'bankName' => 'Kreissparkasse Münster',
            'bankIban' => 'DE11111222223333344444',
            'bankBic' => 'SWSKKEFF',
            'placeOfJurisdiction' => 'Coesfeld',
            'placeOfFulfillment' => 'Coesfeld',
            'executiveDirector' => 'Max Mustermann',
            'companyAddress' => 'Muster AG - Ebbinghoff 10 - 48624 Schöppingen',
        ];

        $configJson = json_encode($defaultConfig);

        $connection->insert('document_base_config', ['id' => $stornoConfigId, 'name' => DocumentTypes::STORNO, 'global' => 1, 'filename_prefix' => DocumentTypes::STORNO . '_', 'type_id' => $stornoId, 'config' => $configJson, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $invoiceConfigId, 'name' => DocumentTypes::INVOICE, 'global' => 1, 'filename_prefix' => DocumentTypes::INVOICE . '_', 'type_id' => $invoiceId, 'config' => $configJson, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $deliveryConfigId, 'name' => DocumentTypes::DELIVERY_NOTE, 'global' => 1, 'filename_prefix' => DocumentTypes::DELIVERY_NOTE . '_', 'type_id' => $deliverNoteId, 'config' => $configJson, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $stornoConfigId, 'document_type_id' => $stornoId]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $invoiceConfigId, 'document_type_id' => $invoiceId]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $deliveryConfigId, 'document_type_id' => $deliverNoteId]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
