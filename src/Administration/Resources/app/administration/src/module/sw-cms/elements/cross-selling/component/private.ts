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
    name: 'sw-cms-el-cross-selling',
    load: () => import('./src/sw-cms-el-cross-selling'),
    public: {
        blocks: [
            'sw_cms_element_cross_selling',
            'sw_cms_element_cross_selling_title',
            'sw_cms_element_cross_selling_content',
            'sw_cms_element_cross_selling_arrow_left',
            'sw_cms_element_cross_selling_product_holder',
            'sw_cms_element_cross_selling_demo_data',
            'sw_cms_element_cross_selling_products',
            'sw_cms_element_cross_selling_arrow_right',
        ],
        computed: [
            'demoProductElement',
            'sliderBoxMinWidth',
            'currentDeviceView',
            'crossSelling',
            'crossSellingProducts',
            'currentDemoEntity',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeUnmountComponent',
            'initResizeObserver',
            'destroyResizeObserver',
            'setSliderRowLimit',
            'getProductEl',
        ],
    },
});
