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
class Migration1565705280ProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565705280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `product_export` (
                `id` BINARY(16) NOT NULL,
                `product_stream_id` BINARY(16) NOT NULL,
                `storefront_sales_channel_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `sales_channel_domain_id` BINARY(16) NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `access_key` VARCHAR(255) NOT NULL,
                `encoding` VARCHAR(255) NOT NULL,
                `file_format` VARCHAR(255) NOT NULL,
                `include_variants` TINYINT(1) NULL DEFAULT \'0\',
                `generate_by_cronjob` TINYINT(1) NOT NULL DEFAULT \'0\',
                `generated_at` DATETIME(3) NULL,
                `interval` INT(11) NOT NULL,
                `header_template` LONGTEXT NULL,
                `body_template` LONGTEXT NULL,
                `footer_template` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `file_name` (`file_name`),
                KEY `fk.product_export.product_stream_id` (`product_stream_id`),
                KEY `fk.product_export.storefront_sales_channel_id` (`storefront_sales_channel_id`),
                KEY `fk.product_export.sales_channel_id` (`sales_channel_id`),
                KEY `fk.product_export.sales_channel_domain_id` (`sales_channel_domain_id`),
                CONSTRAINT `fk.product_export.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_export.storefront_sales_channel_id` FOREIGN KEY (`storefront_sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_export.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_export.sales_channel_domain_id` FOREIGN KEY (`sales_channel_domain_id`) REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->createSalesChannelType($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createSalesChannelType(Connection $connection): void
    {
        $salesChannelTypeId = Uuid::fromHexToBytes('ed535e5722134ac1aa6524f73e26881b');

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = $this->getDeDeLanguageId($connection);

        $connection->insert(
            'sales_channel_type',
            [
                'id' => $salesChannelTypeId,
                'icon_name' => 'default-object-rocket',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_type_translation',
            [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageEN,
                'name' => 'Product comparison',
                'manufacturer' => 'shopware AG',
                'description' => 'Sales channel for product comparison platforms',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_type_translation',
            [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageDE,
                'name' => 'Produktvergleich',
                'manufacturer' => 'shopware AG',
                'description' => 'Verkaufskanal für Produktvergleichsportale',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getDeDeLanguageId(Connection $connection): string
    {
        return (string) $connection->fetchOne(
            'SELECT id FROM language WHERE id != :default',
            ['default' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
    }
}
