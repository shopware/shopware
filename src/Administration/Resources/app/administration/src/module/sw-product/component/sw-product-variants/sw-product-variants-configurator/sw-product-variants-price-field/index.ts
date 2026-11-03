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
    name: 'sw-product-variants-price-field',
    load: () => import('./src/sw-product-variants-price-field'),
    public: {
        props: [
            'price',
            'taxRate',
            'currency',
            'readonly',
        ],
        emits: [
            'price-lock-change',
            'change',
            'price-calculate',
            'price-gross-change',
            'price-net-change',
        ],
        blocks: [
            'sw_product_variants_price_field',
            'sw_product_variants_price_field_gross',
            'sw_product_variants_price_field_lock_button',
            'sw_product_variants_price_field_net',
        ],
        computed: [
            'calculatePriceApiService',
        ],
        methods: [
            'onLockSwitch',
            'onPriceGrossChange',
            'onPriceGrossChangeDebounce',
            'onPriceNetChange',
            'onPriceNetChangeDebounce',
            'convertNetToGross',
            'convertGrossToNet',
            'requestTaxValue',
        ],
    },
});
