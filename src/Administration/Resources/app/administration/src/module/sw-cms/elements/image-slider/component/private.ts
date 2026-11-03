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
    name: 'sw-cms-el-image-slider',
    load: () => import('./src/sw-cms-el-image-slider'),
    public: {
        props: [
            'activeMedia',
        ],
        emits: [
            'active-image-change',
        ],
        blocks: [
            'sw_cms_element_image_slider',
            'sw_cms_element_image_slider_img',
            'sw_cms_element_image_slider_nav',
            'sw_cms_element_image_slider_nav_dots',
            'sw_cms_element_image_slider_nav_arrows',
            'sw_cms_element_image_slider_nav_default',
        ],
        computed: [
            'gridAutoRows',
            'uploadTag',
            'sliderItems',
            'displayModeClass',
            'styles',
            'outsideNavArrows',
            'showNavigationDots',
            'showNavigationArrows',
            'navDotsClass',
            'navArrowsClass',
            'verticalAlignStyle',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'setSliderItem',
            'activeButtonClass',
            'setSliderArrowItem',
        ],
    },
});
