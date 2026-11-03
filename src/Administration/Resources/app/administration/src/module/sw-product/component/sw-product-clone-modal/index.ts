import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-clone-modal',
    load: () => import('./src/sw-product-clone-modal'),
    public: {
        props: [
            'product',
            'productNumberPreview',
        ],
        emits: [
            'clone-finish',
        ],
        blocks: [
            'sw_product_clone_modal',
            'sw_product_clone_modal_description',
            'sw_product_clone_modal_progress_bar',
            'sw_product_clone_modal_progress_bar_description',
        ],
        computed: [
            'progressInPercentage',
            'repository',
        ],
        methods: [
            'createdComponent',
            'duplicate',
            'cloneParent',
            'verifyVariants',
            'getChildrenIds',
            'duplicateVariant',
        ],
    },
});
