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
    name: 'sw-cms-el-buy-box',
    load: () => import('./src/sw-cms-el-buy-box'),
    public: {
        blocks: [
            'sw_cms_el_buy_box',
            'sw_cms_el_buy_box_product_info',
            'sw_cms_el_buy_box_product_price',
            'sw_cms_el_buy_box_product_tax_info',
            'sw_cms_el_buy_box_product_shipping_info',
            'sw_cms_el_buy_box_product_variants',
            'sw_cms_el_buy_box_form',
            'sw_cms_element_buy_box_form_select_quantity',
            'sw_cms_element_buy_box_form_action',
            'sw_cms_element_buy_box_product_number',
            'sw_cms_element_buybox_skeleton',
            'sw_cms_element_buybox_skeleton_info',
            'sw_cms_element_buybox_skeleton_form',
            'sw_cms_element_buybox_skeleton_quantity',
            'sw_cms_element_buy_box_skeleton_action',
            'sw_cms_element_buybox_skeleton_product_number',
        ],
        computed: [
            'product',
            'pageType',
            'isProductPageType',
            'alignStyle',
            'currentDemoEntity',
            'currencyFilter',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
