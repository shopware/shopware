<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;

class Migration1612184092AddUrlLandingPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612184092;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `landing_page_translation`
            ADD COLUMN `url` varchar(255) NULL AFTER `name`
        ');

        $seoUrlTemplate = $connection->fetchAll(
            'SELECT id
            FROM `seo_url_template`
            WHERE `seo_url_template`.`route_name` = :routeName',
            ['routeName' => LandingPageSeoUrlRoute::ROUTE_NAME]
        );

        if (empty($seoUrlTemplate)) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'route_name' => LandingPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => LandingPageDefinition::ENTITY_NAME,
                'template' => LandingPageSeoUrlRoute::DEFAULT_TEMPLATE,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
