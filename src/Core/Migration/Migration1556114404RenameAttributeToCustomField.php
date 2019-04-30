<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556114404RenameAttributeToCustomField extends MigrationStep
{
    private const ENTITIES = [
        'category_translation',
        'cms_block',
        'cms_page_translation',
        'cms_slot_translation',
        'country_state_translation',
        'country_translation',
        'currency_translation',
        'customer',
        'customer_address',
        'customer_group_translation',
        'document',
        'document_type_translation',
        'discount_surcharge_translation',
        'integration',
        'language',
        'locale_translation',
        'media_default_folder',
        'media_folder',
        'media_folder_configuration',
        'media_thumbnail',
        'media_thumbnail_size',
        'media_translation',
        'newsletter_receiver',
        'number_range_translation',
        'number_range_type_translation',
        'order',
        'order_address',
        'order_customer',
        'order_delivery',
        'order_delivery_position',
        'order_line_item',
        'order_transaction',
        'payment_method_translation',
        'plugin_translation',
        'product_configurator_setting',
        'product_manufacturer_translation',
        'product_media',
        'product_price',
        'product_stream_filter',
        'product_stream_translation',
        'product_translation',
        'property_group_option_translation',
        'property_group_translation',
        'rule',
        'rule_condition',
        'sales_channel_domain',
        'sales_channel_translation',
        'sales_channel_type_translation',
        'seo_url',
        'seo_url_template',
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
        'delivery_time_translation',
        'mail_template_type_translation',
    ];

    public function getCreationTimestamp(): int
    {
        return 1556114404;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('RENAME TABLE `attribute` TO `custom_field`');
        $connection->exec('RENAME TABLE `attribute_set` TO `custom_field_set`');
        $connection->exec('RENAME TABLE `attribute_set_relation` TO `custom_field_set_relation`');

        $connection->exec('
            ALTER TABLE `custom_field`
            DROP FOREIGN KEY `fk.attribute.set_id`,
            ADD CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (set_id)
                REFERENCES `custom_field_set` (id) ON UPDATE CASCADE ON DELETE CASCADE'
        );

        $connection->exec('
            ALTER TABLE `custom_field_set_relation`
            DROP FOREIGN KEY `fk.attribute_set_relation.set_id`,
            ADD CONSTRAINT `fk.custom_field_set_relation.set_id` FOREIGN KEY (`set_id`) 
                REFERENCES `custom_field_set` (id) ON UPDATE CASCADE ON DELETE CASCADE'
        );

        // special case
        try {
            $connection->exec('ALTER TABLE `product_price` DROP CONSTRAINT IF EXISTS `json.product_price_rule.attributes`');
        } catch (\Exception $exception) {
            // We ignore the errors because mysql does not support DROP CONSTRAINT.
        }
        try {
            $connection->exec('ALTER TABLE `product_price` DROP CHECK `json.product_price_rule.attributes`');
        } catch (\Exception $exception) {
            // We ignore the errors because only mysql >= 8.0.16 support DROP CHECK.
        }

        // mariadb
        $templateDropConstraintMariaDb = '
            ALTER TABLE `#entity#`
            DROP CONSTRAINT IF EXISTS `json.#entity#.#old_name#`';

        // mysql >= 8.0.16
        $templateDropConstraintNewMysql = '
            ALTER TABLE `#entity#`
            DROP CHECK `json.#entity#.#old_name#`';

        $templateRename = '
            ALTER TABLE `#entity#`
            CHANGE COLUMN `#old_name#` `#new_name#` JSON NULL,
            ADD CONSTRAINT `json.#entity#.#new_name#` CHECK (JSON_VALID(`#new_name#`))';

        foreach (self::ENTITIES as $entity) {
            $params = [
                '#entity#' => $entity,
                '#old_name#' => 'attributes',
                '#new_name#' => 'custom_fields',
            ];

            try {
                $connection->exec(str_replace(array_keys($params), array_values($params), $templateDropConstraintMariaDb));
            } catch (\Exception $exception) {
                // We ignore the errors because mysql does not support DROP CONSTRAINT.
            }
            try {
                $connection->exec(str_replace(array_keys($params), array_values($params), $templateDropConstraintNewMysql));
            } catch (\Exception $exception) {
                // We ignore the errors because only mysql >= 8.0.16 support DROP CHECK.
            }
            $connection->exec(str_replace(array_keys($params), array_values($params), $templateRename));
        }
    }
}
