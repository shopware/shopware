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
    name: 'sw-product-packaging-form',
    load: () => import('./src/sw-product-packaging-form'),
    public: {
        props: [
            'allowEdit',
            'showSettingPackaging',
        ],
        blocks: [
            'sw_product_packaging_form',
            'sw_product_packaging_form_width_field',
            'sw_product_packaging_form_height_field',
            'sw_product_settings_form_length_field',
            'sw_product_settings_form_weight_field',
            'sw_product_price_form_purchase_unit_field',
            'sw_product_price_form_unit_field',
            'sw_product_price_form_reference_unit_field',
            'sw_product_price_form_pack_unit_field',
            'sw_product_price_form_pack_unit_plural_field',
        ],
        computed: [
            'product',
            'parentProduct',
            'isLoading',
        ],
    },
});
