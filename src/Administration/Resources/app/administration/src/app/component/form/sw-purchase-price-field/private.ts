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
    name: 'sw-purchase-price-field',
    load: () => import('./src/sw-purchase-price-field'),
    public: {
        props: [
            'price',
            'compact',
            'taxRate',
            'error',
            'label',
            'disabled',
            'currency',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_purchase_price_field',
        ],
        computed: [
            'purchasePrice',
        ],
        methods: [
            'purchasePriceChanged',
        ],
    },
});
