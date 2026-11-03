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
    name: 'sw-promotion-v2-settings-discount-type',
    load: () => import('./src/sw-promotion-v2-settings-discount-type'),
    public: {
        props: [
            'discount',
            'discountScope',
            'preselectedDiscountType',
            'preselectedApplyDiscountTo',
        ],
        blocks: [
            'sw_promotion_v2_settings_discount_type',
            'sw_promotion_v2_settings_discount_type_type',
            'sw_promotion_v2_settings_discount_type_price',
            'sw_promotion_v2_settings_discount_type_price_suffix',
            'sw_promotion_v2_settings_discount_type_apply_discount_to',
            'sw_promotion_v2_settings_discount_type_max_discount_value',
            'sw_promotion_v2_settings_discount_type_max_discount_value_suffix',
            'sw_promotion_v2_settings_discount_type_advanced_prices_link',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_grid',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_grid_column_name',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_grid_column_price',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_grid_column_price_suffix',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_footer',
            'sw_promotion_v2_settings_discount_type_advanced_prices_modal_footer_button_close',
        ],
        computed: [
            'isPercentageType',
            'labelValue',
            'showAdvancedPricesLink',
            'currencyPriceColumns',
            'currencyRepository',
            'advancedPricesRepo',
            'currencyCriteria',
            'showMaxValueAdvancedPrices',
            'discountTypeOptions',
            'applierOptions',
        ],
        methods: [
            'createdComponent',
            'getDiscountTypeSelection',
            'getApplyDiscountToSelection',
            'onClickAdvancedPrices',
            'clearAdvancedPrices',
            'setCurrencyForDiscountPrices',
            'prepareAdvancedPrices',
            'onMaxValueChanged',
            'onCloseAdvancedPricesModal',
        ],
    },
});
