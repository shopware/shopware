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
    name: 'sw-product-detail-specifications',
    load: () => import('./src/sw-product-detail-specifications'),
    public: {
        blocks: [
            'sw_product_detail_specifications',
            'sw_product_detail_specifications_measurement',
            'sw_product_detail_specifications_measurement_content',
            'sw_product_detail_specifications_measures_packaging',
            'sw_product_detail_specifications_measures_packaging_content',
            'sw_product_detail_specifications_property',
            'sw_product_detail_specifications_essential_characteristics',
            'sw_product_detail_specifications_essential_characteristics_content',
            'sw_product_detail_specifications_custom_products',
            'sw_product_detail_specifications_custom_fields',
            'sw_product_detail_specifications_custom_fields_content',
        ],
        computed: [
            'product',
            'parentProduct',
            'loading',
            'isLoading',
            'customFieldSets',
            'showModeSetting',
            'productStates',
            'productType',
            'isDigitalProduct',
            'customFieldsExists',
            'showCustomFieldsCard',
        ],
        methods: [
            'showProductCard',
        ],
    },
});
