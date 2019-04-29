<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1555487909DocumentConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555487909;
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
      `document_type_id` BINARY(16) NOT NULL,
      `logo_id` BINARY(16) NULL,
      `config` JSON NULL,
      `created_at` DATETIME(3) NOT NULL,
      `updated_at` DATETIME(3) NULL,
      PRIMARY KEY (`id`),
      KEY `idx.document_base_config.type_id` (`document_type_id`),
      CONSTRAINT `json.config` CHECK (JSON_VALID(`config`)),
      CONSTRAINT `fk.document_base_config.type_id` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
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
      `created_at` DATETIME(3) NOT NULL,
      `updated_at` DATETIME(3) NULL,
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

        $connection->insert('document_type', ['id' => $stornoId, 'technical_name' => StornoGenerator::STORNO, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE), 'name' => 'Stornorechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Storno bill', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);

        $stornoConfigId = Uuid::randomBytes();
        $invoiceConfigId = Uuid::randomBytes();
        $deliveryConfigId = Uuid::randomBytes();
        $creditConfigId = Uuid::randomBytes();

        $invoiceId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => InvoiceGenerator::INVOICE]);
        $deliverNoteId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => DeliveryNoteGenerator::DELIVERY_NOTE]);
        $creditNoteId = $connection->fetchColumn('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => CreditNoteGenerator::CREDIT_NOTE]);

        $defaultConfig = [
            'displayPrices' => true,
            'displayFooter' => true,
            'displayHeader' => true,
            'displayLineItems' => true,
            'diplayLineItemPosition' => true,
            'displayPageCount' => true,
            'displayCompanyAddress' => true,
            'pageOrientation' => 'portrait',
            'pageSize' => 'a4',
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

        $deliveryNoteConfig = $defaultConfig;
        $deliveryNoteConfig['displayPrices'] = false;
        $configJson = json_encode($defaultConfig);
        $deliveryNoteConfigJson = json_encode($deliveryNoteConfig);

        $connection->insert('document_base_config', ['id' => $stornoConfigId, 'name' => StornoGenerator::STORNO, 'global' => 1, 'filename_prefix' => StornoGenerator::STORNO . '_', 'document_type_id' => $stornoId, 'config' => $configJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $invoiceConfigId, 'name' => InvoiceGenerator::INVOICE, 'global' => 1, 'filename_prefix' => InvoiceGenerator::INVOICE . '_', 'document_type_id' => $invoiceId, 'config' => $configJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $deliveryConfigId, 'name' => DeliveryNoteGenerator::DELIVERY_NOTE, 'global' => 1, 'filename_prefix' => DeliveryNoteGenerator::DELIVERY_NOTE . '_', 'document_type_id' => $deliverNoteId, 'config' => $deliveryNoteConfigJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $creditConfigId, 'name' => CreditNoteGenerator::CREDIT_NOTE, 'global' => 1, 'filename_prefix' => CreditNoteGenerator::CREDIT_NOTE . '_', 'document_type_id' => $creditNoteId, 'config' => $configJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);

        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $stornoConfigId, 'document_type_id' => $stornoId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $invoiceConfigId, 'document_type_id' => $invoiceId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $deliveryConfigId, 'document_type_id' => $deliverNoteId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $creditConfigId, 'document_type_id' => $creditNoteId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT)]);
    }

    public function updateDestructive(Connection $connection): void
    {
        $definitionNumberRangeTypes = [
            'document_invoice' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Rechnung',
            ],
            'document_storno' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Storno',
            ],
            'document_delivery_note' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Lieferschein',
            ],
            'document_credit_note' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Gutschrift',
            ],
        ];

        $definitionNumberRanges = [
            'document_invoice' => [
                'id' => Uuid::randomHex(),
                'name' => 'Invoices',
                'nameDe' => 'Rechnung',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_invoice']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_storno' => [
                'id' => Uuid::randomHex(),
                'name' => 'Stornos',
                'nameDe' => 'Storno',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_storno']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_delivery_note' => [
                'id' => Uuid::randomHex(),
                'name' => 'DeliveryNotes',
                'nameDe' => 'Lieferschein',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_delivery_note']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_credit_note' => [
                'id' => Uuid::randomHex(),
                'name' => 'CreditNotes',
                'nameDe' => 'Gutschrift',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_credit_note']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
        ];

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($definitionNumberRangeTypes as $typeName => $numberRangeType) {
            $connection->insert(
                'number_range_type',
                [
                    'id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'global' => $numberRangeType['global'],
                    'technical_name' => $typeName,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $typeName,
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        }

        foreach ($definitionNumberRanges as $typeName => $numberRange) {
            $connection->insert(
                'number_range',
                [
                    'id' => Uuid::fromHexToBytes($numberRange['id']),
                    'global' => $numberRange['global'],
                    'type_id' => Uuid::fromHexToBytes($numberRange['typeId']),
                    'pattern' => $numberRange['pattern'],
                    'start' => $numberRange['start'],
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['name'],
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        }
    }
}
