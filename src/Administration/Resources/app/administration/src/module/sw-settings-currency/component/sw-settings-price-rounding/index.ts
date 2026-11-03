import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-price-rounding',
    load: () => import('./src/sw-settings-price-rounding'),
    public: {
        props: [
            'itemRounding',
            'totalRounding',
        ],
        blocks: [
            'sw_settings_price_rounding',
            'sw_settings_price_rounding_header_warning',
            'sw_settings_price_rounding_header_items',
            'sw_settings_price_rounding_subline_items',
            'sw_settings_price_rounding_container_items',
            'sw_settings_price_rounding_container_items_decimals',
            'sw_settings_price_rounding_container_items_interval',
            'sw_settings_price_rounding_container_items_round_for_net',
            'sw_settings_price_rounding_spacer',
            'sw_settings_price_rounding_header_grand',
            'sw_settings_price_rounding_subline_grand',
            'sw_settings_price_rounding_header_info',
            'sw_settings_price_rounding_container_grand',
            'sw_settings_price_rounding_container_grand_decimals',
            'sw_settings_price_rounding_container_grand_interval',
            'sw_settings_price_rounding_container_grand_round_for_net',
        ],
        computed: [
            'itemIntervalDisabled',
            'totalIntervalDisabled',
            'showHeaderInfo',
            'showHeaderWarning',
        ],
        methods: [
            'onChangeDecimals',
        ],
    },
});
