<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549455345AddAttributes extends MigrationStep
{
    private const ENTITIES = [
        'category_translation',
        'cms_block',
        'cms_page_translation',
        'cms_slot_translation',
        'configuration_group_option_translation',
        'configuration_group_translation',
        'country_state_translation',
        'country_translation',
        'currency_translation',
        'customer',
        'customer_address',
        'customer_group_discount',
        'customer_group_translation',
        'discount_surcharge_translation',
        'integration',
        'language',
        'listing_facet_translation',
        'listing_sorting_translation',
        'locale_translation',
        'media_default_folder',
        'media_folder',
        'media_folder_configuration',
        'media_thumbnail',
        'media_thumbnail_size',
        'media_translation',
        'order',
        'order_address',
        'order_customer',
        'order_delivery',
        'order_delivery_position',
        'order_line_item',
        'order_transaction',
        'payment_method_translation',
        'plugin_translation',
        'product_configurator',
        'product_manufacturer_translation',
        'product_media',
        'product_price_rule',
        'product_service',
        'product_stream_filter',
        'product_stream_translation',
        'product_translation',
        'rule',
        'rule_condition',
        'sales_channel_domain',
        'sales_channel_translation',
        'sales_channel_type_translation',
        'search_document',
        'seo_url',
        'shipping_method_price',
        'shipping_method_translation',
        'snippet',
        'snippet_set',
        'state_machine_state_translation',
        'state_machine_transition',
        'state_machine_translation',
        'tax',
        'unit_translation',
        'user',
        'user_access_key',
    ];

    public function getCreationTimestamp(): int
    {
        return 1549455345;
    }

    public function update(Connection $connection): void
    {
        foreach (self::ENTITIES as $entity) {
            $connection->exec(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `attributes` JSON NULL',
                $entity
            ));
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
