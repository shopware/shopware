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
    name: 'sw-meteor-page',
    load: () => import('./src/sw-meteor-page'),
    public: {
        props: [
            'fullWidth',
            'hideIcon',
            'hideSmartBar',
            'fromLink',
        ],
        emits: [
            'new-item-active',
        ],
        slots: [
            'search-bar',
            'smart-bar-back',
            'smart-bar-icon',
            'smart-bar-header',
            'smart-bar-header-meta',
            'smart-bar-description',
            'smart-bar-actions',
            'smart-bar-context-buttons',
            'page-tabs',
            'default',
        ],
        blocks: [
            'sw_meteor_page',
            'sw_meteor_page_seach_bar',
            'sw_meteor_page_notification_center',
            'sw_meteor_page_navigation',
            'sw_meteor_page_smart_bar_icon',
            'sw_meteor_page_smart_bar_title',
            'sw_meteor_page_smart_bar_meta',
            'sw_meteor_page_smart_bar_description',
            'sw_meteor_page_smart_bar_actions',
            'sw_meteor_page_smart_bar_context_buttons',
            'sw_meteor_page_smart_bar_tabs',
            'sw_meteor_page_content',
        ],
        computed: [
            'pageClasses',
            'hasIcon',
            'hasIconOrIconSlot',
            'hasTabs',
            'tabItems',
            'defaultTab',
        ],
        methods: [
            'mountedComponent',
            'emitNewTab',
            'getTabItemsFromSlot',
            'createTabItem',
            'getTabItemDefaultSlotText',
            'getRouteName',
            'isTabItem',
            'initPage',
        ],
    },
});
