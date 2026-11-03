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
    name: 'sw-cms-el-product-slider',
    load: () => import('./src/sw-cms-el-product-slider'),
    public: {
        blocks: [
            'sw_cms_element_product_slider',
            'sw_cms_element_product_slider_title',
            'sw_cms_element_product_slider_content',
            'sw_cms_element_product_slider_arrow_left',
            'sw_cms_element_product_slider_product_holder',
            'sw_cms_element_product_slider_demo_data',
            'sw_cms_element_product_slider_products',
            'sw_cms_element_product_slider_arrow_right',
        ],
        computed: [
            'demoProductElement',
            'hasNavigationArrows',
            'classes',
            'navArrowsClasses',
            'sliderBoxMinWidth',
            'currentDeviceView',
            'verticalAlignStyle',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeUnmountComponent',
            'registerProductHolderResizeObserver',
            'setSliderRowLimit',
            'getProductEl',
        ],
    },
});
