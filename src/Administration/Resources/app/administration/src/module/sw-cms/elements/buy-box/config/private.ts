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
    name: 'sw-cms-el-config-buy-box',
    load: () => import('./src/sw-cms-el-config-buy-box'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_buy_box_config',
            'sw_cms_element_buy_box_config_tabs',
            'sw_cms_element_buy_box_config_mt_tabs',
            'sw_cms_element_buy_box_config_mt_tab_content',
            'sw_cms_element_buy_box_config_mt_content_warning',
            'sw_cms_element_buy_box_config_mt_product_select',
            'sw_cms_element_buy_box_config_mt_product_variant_label',
            'sw_cms_element_buy_box_config_mt_product_select_result_item',
            'sw_cms_element_buy_box_config_mt_product_select_result_label',
            'sw_cms_element_buy_box_config_mt_tab_options',
            'sw_cms_element_buy_box_config_mt_options',
            'sw_cms_element_buy_box_config_tab_content',
            'sw_cms_element_buy_box_config_tab_option',
            'sw_cms_element_buy_box_config_content_warning',
            'sw_cms_element_buy_box_config_product_select',
            'sw_cms_element_buy_box_config_product_selection_label',
            'sw_cms_element_buy_box_config_product_variant_label',
            'sw_cms_element_buy_box_config_product_select_result_item',
            'sw_cms_element_buy_box_config_product_select_result_item_inner',
            'sw_entity_single_select_base_results_list_result_label',
            'sw_cms_element_buy_box_config_options',
        ],
        computed: [
            'tabs',
            'productRepository',
            'productSelectContext',
            'productCriteria',
            'selectedProductCriteria',
            'isProductPage',
            'alignmentOptions',
        ],
        methods: [
            'createdComponent',
            'onProductChange',
            'fetchProduct',
            'deleteProduct',
        ],
    },
});
