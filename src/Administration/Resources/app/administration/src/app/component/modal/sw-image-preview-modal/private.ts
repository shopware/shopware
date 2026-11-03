import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-image-preview-modal',
    load: () => import('./src/sw-image-preview-modal'),
    public: {
        props: [
            'mediaItems',
            'activeItemId',
            'zoomSteps',
            'itemPerPage',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_image_preview_modal',
            'sw_image_preview_modal_element',
            'sw_image_preview_modal_action',
            'sw_image_preview_modal_button_zoom_out',
            'sw_image_preview_modal_button_zoom_reset',
            'sw_image_preview_modal_button_zoom_in',
            'sw_image_preview_modal_button_close',
            'sw_image_preview_modal_image_slider',
            'sw_image_preview_modal_divider',
            'sw_image_preview_modal_thumbnail_slider',
        ],
        computed: [
            'images',
            'maxZoomValue',
        ],
        methods: [
            'createdComponent',
            'afterComponentsMounted',
            'updatedComponent',
            'beforeDestroyComponent',
            'destroyedComponent',
            'buttonClass',
            'getActiveImage',
            'loadImage',
            'onClickClose',
            'onClickZoomIn',
            'onClickZoomOut',
            'onClickReset',
            'onImageSliderChange',
            'onThumbnailSliderChange',
            'setTransition',
            'updateTransform',
            'setActionButtonState',
            'onMouseWheel',
        ],
    },
});
