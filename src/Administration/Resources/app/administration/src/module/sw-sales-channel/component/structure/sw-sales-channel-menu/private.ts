import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-sales-channel-menu',
    load: () => import('./src/sw-sales-channel-menu'),
    public: {
        blocks: [
            'sw_sales_channel_menu',
            'sw_sales_channel_menu_modal',
            'sw_sales_channel_menu_headline',
            'sw_sales_channel_menu_headline_text',
            'sw_sales_channel_menu_headline_context_button',
            'sw_sales_channel_menu_headline_context_button_item',
            'sw_sales_channel_menu_headline_action',
            'sw_sales_channel_menu_navigation',
            'sw_sales_channel_menu_navigation_main_list',
            'sw_sales_channel_menu_navigation_main_items',
            'sw_sales_channel_menu_navigation_item_additional_text',
            'sw_sales_channel_menu_navigation_more_items',
            'sw_sales_channel_menu_navigation_add_channel',
        ],
        computed: [
            'adminMenuStore',
            'isSidebarExpanded',
            'salesChannelRepository',
            'salesChannelModuleColor',
            'canCreateSalesChannels',
            'showAddChannelMenuItem',
            'salesChannelCriteria',
            'moreSalesChannelAvailable',
            'buildMenuTree',
            'moreItemsEntry',
            'salesChannelFavoritesService',
            'salesChannelFavorites',
        ],
        methods: [
            'createdComponent',
            'syncMobileViewport',
            'registerListener',
            'destroyedComponent',
            'getDomainLink',
            'loadEntityData',
            'openSalesChannelModal',
            'openStorefrontLink',
        ],
    },
});
