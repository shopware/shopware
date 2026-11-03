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
    name: 'sw-product-measurement-form',
    load: () => import('./src/sw-product-measurement-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_measurement_form',
            'sw_product_measurement_form_extra_field_before',
            'sw_product_measurement_form_width_height_container',
            'sw_product_measurement_form_width_field',
            'sw_product_measurement_form_height_field',
            'sw_product_measurement_form_length_weight_container',
            'sw_product_measurement_form_length_field',
            'sw_product_measurement_form_weight_field',
            'sw_product_measurement_form_extra_field_after',
        ],
        computed: [
            'product',
            'parentProduct',
            'lengthUnit',
            'weightUnit',
        ],
        methods: [
            'onUpdateLengthUnit',
            'convertWidth',
            'convertHeight',
            'convertLength',
            'onUpdateWeightUnit',
        ],
    },
});
