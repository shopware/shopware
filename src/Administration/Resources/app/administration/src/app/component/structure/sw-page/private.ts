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
    name: 'sw-page',
    load: () => import('./src/sw-page'),
    public: {
        props: [
            'showSmartBar',
            'showSearchBar',
            'headerBorderColor',
        ],
        slots: [
            'search-bar',
            'smart-bar-back',
            'smart-bar-header',
            'language-switch',
            'smart-bar-actions',
            'side-content',
            'content',
            'sidebar',
        ],
        blocks: [
            'sw_page',
            'sw_page_head_area',
            'sw_page_top_bar',
            'sw_page_search_bar',
            'sw_page_slot_search_bar',
            'sw_page_top_bar_actions',
            'sw_page_top_bar_app_button',
            'sw_page_notification_center',
            'sw_page_smart_bar',
            'sw_page_smart_bar_divider',
            'sw_page_smart_bar_back_btn',
            'sw_page_slot_smart_bar_back',
            'sw_page_smart_bar_content',
            'sw_page_smart_bar_content_header',
            'sw_page_slot_smart_bar_header',
            'sw_page_smart_bar_content_language_switch',
            'sw_page_slot_language_switch',
            'sw_page_smart_bar_content_app_actions',
            'sw_page_smart_bar_content_actions',
            'sw_page_slot_smart_bar_actions',
            'sw_page_content',
            'sw_page_slot_side_content',
            'sw_page_slot_content',
            'sw_page_content_sidebar',
            'sw_page_sidebar_slot',
        ],
        computed: [
            'routerBack',
            'pageColor',
            'hasSideContentSlot',
            'hasSidebarSlot',
            'showHeadArea',
            'pageClasses',
            'pageContainerClasses',
            'pageContentClasses',
            'pageOffset',
            'headerStyles',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'updatedComponent',
            'beforeDestroyComponent',
            'readScreenWidth',
            'setSidebarOffset',
            'removeSidebarOffset',
            'setScrollbarOffset',
            'initPage',
        ],
    },
});
