import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-shipping-tax-cost',
    load: () => import('./src/sw-settings-shipping-tax-cost'),
    public: {
        props: [
            'disabled',
        ],
        blocks: [
            'sw_settings_shipping_tax_cost',
            'sw_settings_shipping_detail_shipping_cost_tax_select',
            'sw_settings_shipping_detail_shipping_cost_tax_rate_select',
        ],
        computed: [
            'shippingCostTaxOptions',
            'taxCriteria',
            'taxType',
        ],
        methods: [
            'getTaxLabel',
        ],
    },
});
