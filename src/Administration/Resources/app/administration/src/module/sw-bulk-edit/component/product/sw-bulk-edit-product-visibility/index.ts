import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The sales channel visibility field of the product bulk edit, with a modal for the visibility per sales channel.
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-product-visibility',
    load: () => import('./src/sw-bulk-edit-product-visibility'),
    public: {
        props: [
            'bulkEditProduct',
            'disabled',
        ],
        blocks: [
            'sw_bulk_edit_product_visibility',
            'sw_bulk_edit_product_visibility_select',
            'sw_bulk_edit_product_visibility_advanced',
            'sw_bulk_edit_product_visibility_advanced_modal',
        ],
        computed: [
            'product',
            'productVisibilityRepository',
        ],
        methods: [
            'visibilitiesRemoveInheritanceFunction',
            'openModal',
            'closeModal',
        ],
    },
});
