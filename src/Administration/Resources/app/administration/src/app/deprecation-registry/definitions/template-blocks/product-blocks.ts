/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.product-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of the product modules that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#renamed-cms-element-config-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-bulk-edit-product'],
            blocks: {
                sw_bulk_edit_product_content_meansures_packaging_card: 'sw_bulk_edit_product_content_measurement_card',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-manufacturer-detail'],
            blocks: {
                sw_manufacturer_detail_sidebar: null,
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-product-cross-selling-assignment'],
            sharedNames: ['sw_entity_single_select_base_results_list_result_label'],
            blocks: {
                sw_entity_single_select_base_results_list_result_label:
                    'sw_product_cross_selling_assignment_select_result_item_inner',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-detail'],
            sharedNames: ['sw_product_settings_mode'],
            blocks: {
                sw_product_detail_content_tabs_additional:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_advanced_prices:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_advanced_variants:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_cross_selling:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_general:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_layout:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_reviews:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_seo:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_detail_content_tabs_specifications:
                    'sw_product_detail_content_mt_tabs and add your tab to its productDetailTabs computed',
                sw_product_settings_mode: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-detail-context-prices'],
            blocks: {
                sw_product_detail_prices_empty_state_image:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text_child:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text_empty:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text_inherited:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text_link:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_prices_price_empty_state_text_not_inherited:
                    'sw_product_detail_prices_price_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-detail-cross-selling'],
            blocks: {
                sw_product_detail_cross_selling_empty_state_actions:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content_child:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content_child_inherited:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content_child_inherited_link:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content_child_not_inherited:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_content_empty:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
                sw_product_detail_cross_selling_empty_state_icon:
                    'sw_product_detail_cross_selling_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-modal-delivery'],
            blocks: {
                sw_product_modal_delivery_sidebar_tabs_items:
                    'sw_product_modal_delivery_sidebar_mt_tabs and add your tab to its deliveryTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-modal-variant-generation'],
            blocks: {
                sw_product_modal_variant_generation_sidebar_tabs_item_options:
                    'sw_product_modal_variant_generation_sidebar_mt_tabs and add your tab to its variantGenerationTabs computed',
                sw_product_modal_variant_generation_sidebar_tabs_item_prices:
                    'sw_product_modal_variant_generation_sidebar_mt_tabs and add your tab to its variantGenerationTabs computed',
                sw_product_modal_variant_generation_sidebar_tabs_item_restrictions:
                    'sw_product_modal_variant_generation_sidebar_mt_tabs and add your tab to its variantGenerationTabs computed',
                sw_product_modal_variant_generation_sidebar_tabs_items:
                    'sw_product_modal_variant_generation_sidebar_mt_tabs and add your tab to its variantGenerationTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-packaging-form'],
            blocks: {
                sw_product_packaging_form_height_field:
                    'sw_product_measurement_form_height_field of sw-product-measurement-form',
                sw_product_packaging_form_width_field:
                    'sw_product_measurement_form_width_field of sw-product-measurement-form',
                sw_product_settings_form_length_field:
                    'sw_product_measurement_form_length_field of sw-product-measurement-form',
                sw_product_settings_form_weight_field:
                    'sw_product_measurement_form_weight_field of sw-product-measurement-form',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-properties'],
            blocks: {
                sw_product_properties_empty_state_image: 'sw_product_properties_empty_state',
                sw_product_properties_filled_state_body_empty_state_image:
                    'sw_product_properties_filled_state_body_empty_state',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-product-stream-filter'],
            blocks: {
                sw_product_stream_filter: 'sw_condition_base',
                sw_product_stream_filter_container: 'sw_condition_base_content',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-property-search', 'sw-product-variants-configurator-selection'],
            blocks: {
                sw_property_search_empty_image: 'sw_property_search_empty',
            },
        }),
    ],
});
