<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1612184092AddUrlLandingPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612184092;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `landing_page_translation`
            ADD COLUMN `url` varchar(255) NULL AFTER `name`
        ');

        $seoUrlTemplate = $connection->fetchAllAssociative(
            'SELECT id
            FROM `seo_url_template`
            WHERE `seo_url_template`.`route_name` = :routeName',
            /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Legacy usage in old migration) */
            ['routeName' => 'frontend.landing.page']
        );

        if (empty($seoUrlTemplate)) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Legacy usage in old migration) */
                'route_name' => 'frontend.landing.page',
                'entity_name' => 'landing_page',
                'template' => '{{ landingPage.translated.url }}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
