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
    name: 'sw-maintain-currencies-modal',
    load: () => import('./src/sw-maintain-currencies-modal'),
    public: {
        props: [
            'currencies',
            'prices',
            'defaultPrice',
            'taxRate',
            'hideListPrices',
            'hideRegulationPrices',
            'disabled',
        ],
        emits: [
            'update-prices',
            'modal-close',
        ],
        blocks: [
            'sw_maintain_currencies_modal',
            'sw_maintain_currencies_modal_grid',
            'sw_maintain_currencies_modal_grid_column_name',
            'sw_maintain_currencies_modal_grid_column_name_inherit_field',
            'sw_maintain_currencies_modal_grid_column_name_item_name',
            'sw_maintain_currencies_modal_grid_column_price',
            'sw_maintain_currencies_modal_grid_column_price_field_label',
            'sw_maintain_currencies_modal_grid_column_price_field',
            'sw_maintain_currencies_modal_footer',
            'sw_maintain_currencies_modal_footer_button_cancel',
            'sw_maintain_currencies_modal_footer_button_apply',
        ],
        computed: [
            'maintainCurrencyColumns',
        ],
        methods: [
            'createdComponent',
            'loadCurrencies',
            'updateCurrencyCollectionFromCurrencies',
            'sortCurrencies',
            'convertPrice',
            'isCurrencyInherited',
            'onInheritanceRestore',
            'onInheritanceRemove',
            'onCancel',
            'onApply',
        ],
    },
});
