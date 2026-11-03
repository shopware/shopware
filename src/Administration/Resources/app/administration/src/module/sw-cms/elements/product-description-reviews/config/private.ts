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
    name: 'sw-cms-el-config-product-description-reviews',
    load: () => import('./src/sw-cms-el-config-product-description-reviews'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_product_description_reviews_config',
            'sw_cms_element_product_description_reviews_config_tabs',
            'sw_cms_element_product_description_reviews_config_mt_tabs',
            'sw_cms_element_product_description_reviews_config_mt_content',
            'sw_cms_element_product_description_reviews_mt_warning',
            'sw_cms_element_product_description_reviews_config_mt_product_select',
            'sw_cms_element_product_description_reviews_config_mt_product_variant_label',
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_item',
            'sw_cms_element_product_description_reviews_config_mt_product_select_result_label',
            'sw_cms_el_product_description_rating_config_mt_options',
            'sw_cms_el_product_description_rating_config_mt_options_alignment',
            'sw_cms_element_product_description_reviews_config_tab_content',
            'sw_cms_element_product_description_reviews_config_tab_options',
            'sw_cms_element_product_description_reviews_config_content',
            'sw_cms_element_product_description_reviews_warning',
            'sw_cms_element_product_description_reviews_config_product_select',
            'sw_cms_element_product_description_reviews_config_product_selection_label',
            'sw_entity_single_select_variant_selected_item',
            'sw_cms_element_product_description_reviews_config_product_select_result_item',
            'sw_entity_single_select_variant_result_item',
            'sw_cms_element_product_description_reviews_config_product_select_result_item_inner',
            'sw_entity_single_select_base_results_list_result_label',
            'sw_cms_el_product_description_rating_config_options',
            'sw_cms_el_product_description_rating_config_options_alignment',
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
        ],
    },
});
