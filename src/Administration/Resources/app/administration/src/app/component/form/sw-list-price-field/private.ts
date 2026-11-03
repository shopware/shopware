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
    name: 'sw-list-price-field',
    load: () => import('./src/sw-list-price-field'),
    public: {
        props: [
            'price',
            'purchasePrices',
            'defaultPrice',
            'label',
            'taxRate',
            'currency',
            'compact',
            'error',
            'disabled',
            'enableInheritance',
            'disableSuffix',
            'vertical',
            'hideListPrices',
            'hidePurchasePrices',
            'hideRegulationPrices',
            'showSettingPrice',
        ],
        blocks: [
            'sw_list_price_field',
            'sw_list_price_field_price',
            'sw_product_price_form_purchase_price_field',
            'sw_product_price_form_purchase_price_field_content',
            'sw_list_price_field_list_price',
            'sw_list_price_field_regulation_price',
        ],
        computed: [
            'priceForCurrency',
            'listPrice',
            'regulationPrice',
            'defaultListPrice',
            'defaultRegulationPrice',
            'isInherited',
            'listPriceHelpText',
            'regulationPriceHelpText',
        ],
        methods: [
            'listPriceChanged',
            'regulationPriceChanged',
            'convertPrice',
        ],
    },
});
