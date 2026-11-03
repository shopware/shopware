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
    name: 'sw-settings-listing-default-sales-channel',
    load: () => import('./src/sw-settings-listing-default-sales-channel'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_settings_listing_default_sales_channel',
            'sw_settings_listing_default_sales_channel_loader',
            'sw_settings_listing_default_sales_channel_description',
            'sw_settings_listing_default_sales_channel_select',
            'sw_settings_listing_default_sales_channeld_setting',
            'sw_settings_listing_default_sales_channel_setting_active',
            'sw_settings_listing_default_sales_channel_setting_visibility',
            'sw_settings_listing_default_sales_channel_select_visibiliy_modal',
        ],
        computed: [
            'salesChannelRepository',
            'salesChannel',
        ],
        methods: [
            'createdComponent',
            'fetchSalesChannelsSystemConfig',
            'displayAdvancedVisibility',
            'closeAdvancedVisibility',
            'saveSalesChannelVisibilityConfig',
            'updateSalesChannel',
        ],
    },
});
