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
    name: 'sw-settings-shipping-price-matrices',
    load: () => import('./src/sw-settings-shipping-price-matrices'),
    public: {
        props: [
            'disabled',
        ],
        blocks: [
            'sw_settings_shipping_price_matrices',
            'sw_settings_shipping_price_matrices_price_matrix',
            'sw_settings_shipping_detail_advanced_prices_actions',
            'sw_settings_shipping_detail_advanced_prices_actions_add_button',
        ],
        computed: [
            'shippingMethod',
            'shippingPriceGroups',
            'usedRules',
            'unrestrictedPriceMatrixExists',
            'newPriceMatrixExists',
            'ruleRepository',
            'ruleFilter',
            'shippingPriceRepository',
            'isLoaded',
        ],
        methods: [
            'onAddNewPriceGroup',
            'onDeletePriceMatrix',
            'onDuplicatePriceMatrix',
        ],
    },
});
