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
    name: 'sw-settings-listing-visibility-detail',
    load: () => import('./src/sw-settings-listing-visibility-detail'),
    public: {
        props: [
            'disabled',
            'config',
        ],
        blocks: [
            'sw_product_visibility_detail',
            'sw_settings_listing_visibility_detail_columns',
            'sw_settings_listing_visibility_detail_columns_sales_channel',
            'sw_settings_listing_visibility_detail_columns_sales_channel_label',
            'sw_settings_listing_visibility_detail_columns_all',
            'sw_settings_listing_visibility_detail_columns_search_only',
            'sw_settings_listing_visibility_detail_columns_link_only',
            'sw_settings_listing_visibility_detail_pagination',
        ],
        computed: [
            'salesChannelRepository',
            'truncateFilter',
        ],
        methods: [
            'createdComponent',
            'onPageChange',
            'changeVisibilityValue',
            'fetchSalesChannels',
        ],
    },
});
