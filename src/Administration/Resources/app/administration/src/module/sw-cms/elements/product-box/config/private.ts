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
    name: 'sw-cms-el-config-product-box',
    load: () => import('./src/sw-cms-el-config-product-box'),
    public: {
        emits: [
            'element-update',
        ],
        slots: [
            'result-item',
        ],
        blocks: [
            'sw_cms_element_product_box_config',
            'sw_cms_element_product_box_config_product_select',
            'sw_cms_element_product_box_config_product_selection_label',
            'sw_cms_element_product_box_config_product_select_result_item',
            'sw_cms_element_product_box_config_product_select_result_item_inner',
            'sw_entity_single_select_base_results_list_result_label',
            'sw_cms_element_product_box_config_layout_select',
            'sw_cms_element_product_box_config_displaymode_select',
            'sw_cms_element_product_box_config_settings_vertical_align',
        ],
        computed: [
            'productRepository',
            'productSelectContext',
            'productCriteria',
            'boxLayoutOptions',
            'displayModeOptions',
            'verticalAlignOptions',
        ],
        methods: [
            'createdComponent',
            'onProductChange',
        ],
    },
});
