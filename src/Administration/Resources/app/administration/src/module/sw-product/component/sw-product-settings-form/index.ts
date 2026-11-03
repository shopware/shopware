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
    name: 'sw-product-settings-form',
    load: () => import('./src/sw-product-settings-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_settings_form',
            'sw_product_settings_form_content',
            'sw_product_settings_form_release_date_field',
            'sw_product_settings_form_ean_field',
            'sw_product_settings_form_manufacturer_number_field',
        ],
        computed: [
            'product',
            'parentProduct',
        ],
    },
});
