<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1620634856UpdateRolePrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'newsletter_recipient.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'country.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'customer_groups.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'cms.viewer' => [
            'landing_page:read',
            'product_cross_selling_assigned_products:read',
            'product_manufacturer:read',
        ],
        'cms.editor' => [
            'landing_page:update',
        ],
        'delivery_times.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'document.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'event_action.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'integration.editor' => [
            'integration_role:create',
            'integration_role:delete',
        ],
        'language.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'number_ranges.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'payment.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'product.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'number_range:read',
            'number_range_type:read',
            // cms.viewer permissions
            'cms_page:read',
            'media:read',
            'cms_section:read',
            'category:read',
            'landing_page:read',
            'media_default_folder:read',
            'media_folder:read',
            'sales_channel:read',
            'cms_block:read',
            'cms_slot:read',
            'product_sorting:read',
            'product:read',
            'property_group:read',
            'property_group_option:read',
            'product_media:read',
            'delivery_time:read',
            'product_cross_selling:read',
            'product_cross_selling_assigned_products:read',
            'product_manufacturer:read',
        ],
        'product_manufacturer.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'users_and_permissions.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'product_feature_sets.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'promotion.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'system.viewer' => [
            'plugin:update',
            'system:clear:cache',
            'system_config:read',
        ],
        'order.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'property.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'product_search_config.viewer' => [
            'product_search_config_field:read',
            'custom_field_set:read',
            'product_search_keyword:read',
            'product:read',
            'sales_channel:read',
            'custom_field:read',
        ],
        'product_search_config.editor' => [
            'product_search_config_field:update',
            'product_search_keyword:update',
        ],
        'product_search_config.creator' => [
            'product_search_config_field:create',
            'product_search_keyword:create',
        ],
        'product_search_config.deleter' => [
            'product_search_config_field:delete',
            'product_search_keyword:delete',
            'product_search_config:update',
        ],
        'currencies.viewer' => [
            'currency_country_rounding:read',
            'country:read',
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'currencies.editor' => [
            'currency_country_rounding:update',
            'currency_country_rounding:delete',
        ],
        'review.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'product_stream.viewer' => [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
        'salutation.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
        'shipping.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1620634856;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
