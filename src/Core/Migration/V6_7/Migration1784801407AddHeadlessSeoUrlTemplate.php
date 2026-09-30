<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Adds the `is_headless` discriminator to `seo_url_template` and seeds the default store-api templates
 * used by headless (API type) sales channels. Each store-api default mirrors the template of its storefront
 * counterpart, so merchants get a sensible relative default that is resolved against the sales channel domain.
 *
 * @internal
 */
#[Package('inventory')]
class Migration1784801407AddHeadlessSeoUrlTemplate extends MigrationStep
{
    use AddColumnTrait;

    /**
     * The headless store-api defaults to seed, each with the literal default template used as a fallback when
     * no storefront counterpart (matched by entity) exists to copy from.
     *
     * @var list<array{entity: string, storeApiRoute: string, default: string}>
     */
    private const STORE_API_DEFAULTS = [
        [
            'entity' => 'product',
            'storeApiRoute' => 'store-api.product.detail',
            'default' => '{{ product.translated.name }}/{{ product.productNumber }}',
        ],
        [
            'entity' => 'category',
            'storeApiRoute' => 'store-api.category.detail',
            'default' => '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}',
        ],
        [
            'entity' => 'landing_page',
            'storeApiRoute' => 'store-api.landing-page.detail',
            'default' => '{{ landingPage.translated.url }}',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1784801407;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'seo_url_template', 'is_headless', 'TINYINT(1)', false, '0');

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (self::STORE_API_DEFAULTS as $config) {
            $exists = $connection->fetchOne(
                'SELECT 1 FROM `seo_url_template` WHERE `route_name` = :route AND `sales_channel_id` IS NULL LIMIT 1',
                ['route' => $config['storeApiRoute']]
            );

            if ($exists) {
                continue;
            }

            // Copy the template from the storefront default of the same entity (is_headless = 0), so a merchant
            // who customized the storefront template gets the same relative default for headless.
            $template = $connection->fetchOne(
                'SELECT `template` FROM `seo_url_template`
                 WHERE `entity_name` = :entity AND `sales_channel_id` IS NULL AND `is_headless` = 0
                 LIMIT 1',
                ['entity' => $config['entity']]
            );

            $template = \is_string($template) && $template !== '' ? $template : $config['default'];

            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => null,
                'entity_name' => $config['entity'],
                'route_name' => $config['storeApiRoute'],
                'template' => $template,
                'is_valid' => 1,
                'is_headless' => 1,
                'created_at' => $now,
            ]);
        }
    }
}
