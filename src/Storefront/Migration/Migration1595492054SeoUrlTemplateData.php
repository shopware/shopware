<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class Migration1595492054SeoUrlTemplateData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595492054;
    }

    public function update(Connection $connection): void
    {
        $stmt = $connection->prepare('SELECT count(`id`) FROM seo_url_template WHERE `entity_name` = ? AND `template` = ? AND `route_name` = ?');
        $stmt->execute([
            'product',
            ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
            ProductPageSeoUrlRoute::ROUTE_NAME,
        ]);

        if ((int) $stmt->fetch(\PDO::FETCH_COLUMN) === 0) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => null,
                'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => 'product',
                'template' => ProductPageSeoUrlRoute::DEFAULT_TEMPLATE,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $stmt->execute([
            'category',
            NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            NavigationPageSeoUrlRoute::ROUTE_NAME,
        ]);

        if ((int) $stmt->fetch(\PDO::FETCH_COLUMN) === 0) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => null,
                'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => 'category',
                'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
