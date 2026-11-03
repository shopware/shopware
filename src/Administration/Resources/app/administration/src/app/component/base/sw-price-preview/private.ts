import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-price-field,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-price-preview',
    extends: 'sw-price-field',
    load: () => import('./src/sw-price-preview'),
    public: {
        props: [
            'value',
            'allowModal',
            'defaultPrice',
            'hideListPrices',
            'taxRate',
            'currency',
            'validation',
            'label',
            'compact',
            'error',
            'disabled',
            'disableSuffix',
            'grossLabel',
            'netLabel',
            'name',
            'allowEmpty',
            'inherited',
            'grossHelpText',
            'netHelpText',
        ],
        emits: [
            'change',
            'price-lock-change',
            'price-calculate',
            'price-gross-change',
            'price-net-change',
            'calculating',
        ],
        blocks: [
            'sw_price_field',
            'sw_price_preview',
            'sw_price_field_gross',
            'sw_price_field_lock_button',
            'sw_price_field_net',
        ],
        computed: [
            'currencyFilter',
            'calculatePriceApiService',
            'priceForCurrency',
            'attributesWithoutListeners',
            'isInherited',
            'isDisabled',
            'labelGross',
            'labelNet',
            'grossError',
            'netError',
            'grossFieldName',
            'netFieldName',
        ],
        methods: [
            'onLockSwitch',
            'onEndsWithDecimalSeparator',
            'onPriceGrossInputChange',
            'onPriceNetInputChange',
            'onPriceGrossChange',
            'onPriceNetChange',
            'convertNetToGross',
            'convertGrossToNet',
            'requestTaxValue',
            'convertPrice',
            'keymonitor',
            'onCloseModal',
            'onPriceGrossChangeDebounce',
            'onPriceNetChangeDebounce',
        ],
    },
});
