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
    name: 'sw-product-image',
    load: () => import('./src/sw-product-image'),
    public: {
        props: [
            'mediaId',
            'isSpatial',
            'isArReady',
            'isCover',
            'isPlaceholder',
            'showCoverLabel',
        ],
        emits: [
            'sw-product-image-cover',
            'sw-product-image-delete',
        ],
        blocks: [
            'sw_product_image',
            'sw_product_image_preview',
            'sw_product_image_preview_spatial_labels',
            'sw_product_image_context_button',
            'sw_product_image_context',
            'sw_product_image_context_cover_action',
            'sw_product_image_context_delete_action',
            'sw_product_image_placeholder',
        ],
        computed: [
            'productImageClasses',
        ],
    },
});
