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
    name: 'sw-product-price-form',
    load: () => import('./src/sw-product-price-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_price_form',
            'sw_product_price_form_fields',
            'sw_product_price_form_tax_field',
            'sw_product_price_form_price_field',
            'sw_product_price_form_link',
            'sw_product_price_form_advanced_prices_link',
            'sw_product_price_form_maintain_currencies_link',
            'sw_product_price_form_maintain_currencies_modal',
        ],
        computed: [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate',
            'showModeSetting',
            'product',
            'parentProduct',
            'taxes',
            'currencies',
            'taxRateHelpText',
            'prices',
            'parentPrices',
            'taxRateOptions',
        ],
        methods: [
            'removePriceInheritation',
            'inheritationCheckFunction',
            'onMaintainCurrenciesClose',
            'getTaxLabel',
            'updatePrices',
        ],
    },
});
