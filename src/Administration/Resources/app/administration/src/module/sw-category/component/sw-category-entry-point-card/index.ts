import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-category-entry-point-card',
    load: () => import('./src/sw-category-entry-point-card'),
    public: {
        props: [
            'category',
            'isLoading',
        ],
        blocks: [
            'sw_category_entry_point_card',
            'sw_category_entry_point_card_selection',
            'sw_category_entry_point_card_navigation_headline',
            'sw_category_entry_point_card_navigation_list',
            'sw_category_detail_entry_point_navigation_list_link',
            'sw_category_entry_point_card_sales_channel_selection',
            'sw_category_entry_point_card_button_configure_home',
            'sw_category_entry_point_card_configure_home_modal',
        ],
        computed: [
            'entryPoints',
            'associatedCollection',
            'helpText',
            'hasExistingNavigation',
            'salesChannelSelectionLabel',
            'salesChannelCriteria',
        ],
        methods: [
            'getInitialEntryPointFromCategory',
            'onEntryPointChange',
            'onSalesChannelChange',
            'resetSalesChannelCollections',
            'openConfigureHomeModal',
            'closeConfigureHomeModal',
        ],
    },
});
