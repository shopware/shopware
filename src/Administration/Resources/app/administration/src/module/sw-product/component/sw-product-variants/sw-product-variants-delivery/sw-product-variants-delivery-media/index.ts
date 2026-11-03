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
    name: 'sw-product-variants-delivery-media',
    load: () => import('./src/sw-product-variants-delivery-media'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        blocks: [
            'sw_product_variants_delivery_media',
            'sw_product_variants_delivery_media_groups',
            'sw_product_variants_delivery_media_media',
            'sw_product_variants_delivery_media_data_grid',
            'sw_product_variants_delivery_media_data_grid_column_name',
            'sw_product_variants_delivery_media_data_grid_column_option_media',
            'sw_product_variants_delivery_media_data_grid_column_option_media_listener',
            'sw_product_variants_delivery_media_data_grid_column_option_media_upload',
            'sw_product_variants_delivery_media_loader',
        ],
        computed: [
            'selectedGroupsSorted',
            'optionColumns',
            'activeOptions',
        ],
        methods: [
            'onUploadsAdded',
            'successfulUpload',
            'removeMedia',
            'setMedia',
            'onChangeGroupListing',
        ],
    },
});
