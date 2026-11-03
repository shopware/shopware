import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-image-slider',
    load: () => import('./src/sw-image-slider'),
    public: {
        props: [
            'images',
            'canvasWidth',
            'canvasHeight',
            'gap',
            'elementPadding',
            'navigationType',
            'enableDescriptions',
            'overflow',
            'rewind',
            'bordered',
            'rounded',
            'autoWidth',
            'itemPerPage',
            'initialIndex',
            'arrowStyle',
            'buttonStyle',
            'displayMode',
        ],
        emits: [
            'image-change',
        ],
        blocks: [
            'sw_image_slider',
            'sw_image_slider_image_container',
            'sw_image_slider_image_container_scrollable',
            'sw_image_slider_images',
            'sw_image_slider_image_images_container',
            'sw_image_slider_image',
            'sw_image_slider_element_description',
            'sw_image_slider_image_container_arrows',
            'sw_image_slider_image_container_arrow_left',
            'sw_image_slider_image_container_arrow_right',
            'sw_image_slider_button_container',
            'sw_image_slider_buttons',
        ],
        computed: [
            'totalPage',
            'remainder',
            'buttonList',
            'wrapperStyles',
            'componentStyles',
            'containerStyles',
            'scrollableContainerStyles',
            'imageStyles',
            'buttonClasses',
            'showButtons',
            'showArrows',
        ],
        methods: [
            'setCurrentPageNumber',
            'isImageObject',
            'hasValidDescription',
            'getImage',
            'imageAlt',
            'goToPreviousImage',
            'goToNextImage',
            'elementClasses',
            'elementStyles',
            'imageClasses',
            'borderStyles',
            'onSetCurrentItem',
            'isHiddenItem',
        ],
    },
});
