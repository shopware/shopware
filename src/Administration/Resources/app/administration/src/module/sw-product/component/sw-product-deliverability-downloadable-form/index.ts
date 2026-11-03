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
    name: 'sw-product-deliverability-downloadable-form',
    load: () => import('./src/sw-product-deliverability-downloadable-form'),
    public: {
        props: [
            'disabled',
        ],
        blocks: [
            'sw_product_deliverability_downloadable_form',
            'sw_product_deliverability_downloadable_form_delivery_time_field',
            'sw_product_deliverability_downloadable_form_is_closeout_field',
            'sw_product_deliverability_downloadable_form_stock_field',
            'sw_product_deliverability_downloadable_form_available_stock_field',
            'sw_product_deliverability_downloadable_form_order_quantity_switch',
            'sw_product_deliverability_downloadable_form_min_purchase_field',
            'sw_product_deliverability_downloadable_form_purchase_steps_field',
            'sw_product_deliverability_downloadable_form_max_purchase_field',
        ],
        computed: [
            'product',
            'parentProduct',
            'showModeSetting',
            'showStockSetting',
            'isLimitedToOneUnit',
        ],
        methods: [
            'createdComponent',
            'onSwitchInput',
            'onOrderQuantitySwitchInput',
            'getOrderQuantity',
            'setOrderQuantity',
            'getInheritedValue',
        ],
    },
});
