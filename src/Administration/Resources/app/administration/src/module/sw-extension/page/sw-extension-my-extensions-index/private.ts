import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-my-extensions-index',
    load: () => import('./src/sw-extension-my-extensions-index'),
    public: {
        blocks: [
            'sw_extension_my_extensions_index',
            'sw_extension_my_extensions_index_smart_bar_header',
            'sw_extension_my_extensions_index_smart_bar_search_slot',
            'sw_extension_my_extensions_index_smart_bar_search_slot_search_bar',
            'sw_extension_my_extensions_index_smart_bar_tabs',
            'sw_extension_my_extensions_index_smart_bar_tabs_app',
            'sw_extension_my_extensions_index_smart_bar_tabs_theme',
            'sw_extension_my_extensions_index_smart_bar_tabs_recommendation',
            'sw_extension_my_extensions_index_smart_bar_tabs_account',
            'sw_extension_my_extensions_index_smart_bar_actions',
            'sw_extension_my_extensions_index_smart_bar_actions_file_upload',
            'sw_extension_my_extensions_index_body',
        ],
        computed: [
            'searchValue',
            'queryParams',
            'extensionManagementDisabled',
        ],
        methods: [
            'onSearch',
            'updateRouteQueryTerm',
        ],
    },
});
