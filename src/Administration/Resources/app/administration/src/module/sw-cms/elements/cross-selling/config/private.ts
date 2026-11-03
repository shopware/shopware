import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-el-config-cross-selling',
    load: () => import('./src/sw-cms-el-config-cross-selling'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_cross_selling_config',
            'sw_cms_element_cross_selling_config_tabs',
            'sw_cms_element_cross_selling_config_mt_tabs',
            'sw_cms_element_cross_selling_config_mt_content',
            'sw_cms_element_cross_selling_config_mt_content_warning_text',
            'sw_cms_element_cross_selling_config_mt_content_products',
            'sw_cms_element_cross_selling_config_mt_product_variant_label',
            'sw_cms_element_cross_selling_config_mt_product_select_result_item',
            'sw_cms_element_cross_selling_config_mt_product_select_result_label',
            'sw_cms_element_cross_selling_config_mt_options',
            'sw_cms_element_cross_selling_config_mt_options_box_layout',
            'sw_cms_element_cross_selling_config_mt_options_display_mode',
            'sw_cms_element_cross_selling_config_mt_options_min_width',
            'sw_cms_element_cross_selling_config_mt_options_speed',
            'sw_cms_element_cross_selling_config_tab_content',
            'sw_cms_element_cross_selling_config_tab_options',
            'sw_cms_element_cross_selling_config_content',
            'sw_cms_element_cross_selling_config_content_warning_text',
            'sw_cms_element_cross_selling_config_content_products',
            'sw_cms_element_cross_selling_config_content_products_selection_label',
            'sw_entity_single_select_variant_selected_item',
            'sw_cms_element_cross_selling_config_content_products_select_result_item',
            'sw_entity_single_select_variant_result_item',
            'sw_cms_element_cross_selling_config_content_products_select_result_item_inner',
            'sw_entity_single_select_base_results_list_result_label',
            'sw_cms_element_cross_selling_config_options',
            'sw_cms_element_cross_selling_config_options_box_layout',
            'sw_cms_element_cross_selling_config_options_display_mode',
            'sw_cms_element_cross_selling_config_options_min_width',
            'sw_cms_element_cross_selling_config_options_speed',
        ],
        computed: [
            'tabs',
            'productRepository',
            'productSelectContext',
            'productCriteria',
            'selectedProductCriteria',
            'isProductPageType',
            'boxLayoutOptions',
            'displayModeOptions',
        ],
        methods: [
            'createdComponent',
            'onProductChange',
            'fetchProduct',
            'deleteProduct',
        ],
    },
});
