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
    name: 'sw-cms-el-product-description-reviews',
    load: () => import('./src/sw-cms-el-product-description-reviews'),
    public: {
        blocks: [
            'sw_cms_element_product_description_reviews',
            'sw_cms_element_product_description_reviews_tabs',
            'sw_cms_element_product_description_reviews_placeholder',
            'sw_cms_element_product_description_reviews_placeholder_header',
            'sw_cms_element_product_description_reviews_placeholder_listing',
            'sw_cms_element_product_description_reviews_detail',
            'sw_cms_element_product_description_reviews_detail_header',
            'sw_cms_element_product_description_reviews_detail_listing',
        ],
        computed: [
            'product',
            'pageType',
            'isProductPageType',
            'alignStyle',
            'currentDemoEntity',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
