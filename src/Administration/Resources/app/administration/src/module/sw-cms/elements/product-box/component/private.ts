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
    name: 'sw-cms-el-product-box',
    load: () => import('./src/sw-cms-el-product-box'),
    public: {
        blocks: [
            'sw_cms_element_product_box',
            'sw_cms_element_product_box_content',
            'sw_cms_element_product_box_badges',
            'sw_cms_element_product_box_media',
            'sw_cms_element_product_box_info_skeleton',
            'sw_cms_element_product_box_name_skeleton',
            'sw_cms_element_product_box_description_skeleton',
            'sw_cms_element_product_box_price_skeleton',
            'sw_cms_element_product_box_actions_skeleton',
            'sw_cms_element_product_box_info',
            'sw_cms_element_product_box_name',
            'sw_cms_element_product_box_description',
            'sw_cms_element_product_box_price_info',
            'sw_cms_element_product_box_price_unit',
            'sw_cms_element_product_box_price',
            'sw_cms_element_product_box_actions',
        ],
        computed: [
            'product',
            'displaySkeleton',
            'mediaUrl',
            'altTag',
            'displayModeClass',
            'verticalAlignStyle',
            'assetFilter',
            'truncateFilter',
            'currencyFilter',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
