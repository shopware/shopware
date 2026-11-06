/**
 * @sw-package framework
 */

import { renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-product-listing',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-product-listing that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_available_sortings',
            to: 'sw_cms_element_product_listing_config_mt_available_sortings',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_default_sorting',
            to: 'sw_cms_element_product_listing_config_mt_default_sorting',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_by_manufacturer',
            to: 'sw_cms_element_product_listing_config_mt_filter_by_manufacturer',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_by_price',
            to: 'sw_cms_element_product_listing_config_mt_filter_by_price',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_by_rating',
            to: 'sw_cms_element_product_listing_config_mt_filter_by_rating',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_by_wrapper',
            to: 'sw_cms_element_product_listing_config_mt_filter_by_wrapper',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_empty_state',
            to: 'sw_cms_element_product_listing_config_mt_filter_empty_state',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_for_free_shipping',
            to: 'sw_cms_element_product_listing_config_mt_filter_for_free_shipping',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_info',
            to: 'sw_cms_element_product_listing_config_mt_filter_info',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_properties_as_filter',
            to: 'sw_cms_element_product_listing_config_mt_filter_properties_as_filter',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_properties_as_filter_info_text',
            to: 'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_info_text',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_properties_as_filter_switch',
            to: 'sw_cms_element_product_listing_config_mt_filter_properties_as_filter_switch',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_properties_wrapper',
            to: 'sw_cms_element_product_listing_config_mt_filter_properties_wrapper',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_property_grid',
            to: 'sw_cms_element_product_listing_config_mt_filter_property_grid',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_property_grid_column_status',
            to: 'sw_cms_element_product_listing_config_mt_filter_property_grid_column_status',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_property_grid_columns',
            to: 'sw_cms_element_product_listing_config_mt_filter_property_grid_columns',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_property_grid_pagination',
            to: 'sw_cms_element_product_listing_config_mt_filter_property_grid_pagination',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_property_search',
            to: 'sw_cms_element_product_listing_config_mt_filter_property_search',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_filter_spacer',
            to: 'sw_cms_element_product_listing_config_mt_filter_spacer',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_info',
            to: 'sw_cms_element_product_listing_config_mt_info',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_layout_select',
            to: 'sw_cms_element_product_listing_config_mt_layout_select',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_show_sorting',
            to: 'sw_cms_element_product_listing_config_mt_show_sorting',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_sorting_grid',
            to: 'sw_cms_element_product_listing_config_mt_sorting_grid',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_product_listing_config_use_default_sorting',
            to: 'sw_cms_element_product_listing_config_mt_use_default_sorting',
            components: ['sw-cms-el-config-product-listing'],
        }),
        renameTemplateBlock({
            from: 'sw_entity_multi_select_base_results_list_result_label',
            to: 'sw_cms_element_product_listing_config_mt_available_sortings_result_label',
            components: ['sw-cms-el-config-product-listing'],
            onlyInComponents: true,
        }),
    ],
});
