<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class Migration1551969523SeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551969523;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `seo_url_template` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `sales_channel_id` BINARY(16) NULL,
              `route_name` VARCHAR(255) NOT NULL,
              `entity_name` VARCHAR(64) NOT NULL,
              `template` VARCHAR(750) NOT NULL,
              `is_valid` TINYINT(1) NOT NULL DEFAULT 1,              
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              CONSTRAINT `uniq.seo_url_template.route_name`
                UNIQUE (`sales_channel_id`, `route_name`),
              CONSTRAINT `fk.seo_url_template.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
              CONSTRAINT `json.seo_url_template.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            )
        ');

        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => null,
            'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'entity_name' => 'product',
            'template' => ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => null,
            'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
            'entity_name' => 'category',
            'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
