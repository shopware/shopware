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
    name: 'sw-product-variants-configurator-prices',
    load: () => import('./src/sw-product-variants-configurator-prices'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        blocks: [
            'sw_product_variants_configurator_prices',
            'sw_product_variants_configurator_prices_groups',
            'sw_product_variants_configurator_prices_prices',
            'sw_product_variants_configurator_prices_search',
            'sw_product_variants_configurator_prices_search_field',
            'sw_product_variants_configurator_prices_grid_wrapper',
            'sw_product_variants_configurator_prices_data_grid',
            'sw_product_variants_configurator_prices_column_name',
            'sw_product_variants_configurator_prices_column_currency',
            'sw_product_variants_configurator_prices_actions',
            'sw_product_variants_configurator_prices_actions_items',
            'sw_product_variants_configurator_prices_loader',
        ],
        computed: [
            'currencyRepository',
            'currenciesList',
            'optionColumns',
        ],
        methods: [
            'onSearchTermChange',
            'mountedComponent',
            'loadCurrencies',
            'getOptionsForGroup',
            'resetSurcharges',
            'getCurrencyOfOption',
        ],
    },
});
