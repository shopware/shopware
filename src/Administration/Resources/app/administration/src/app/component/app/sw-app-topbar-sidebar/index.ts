import template from './sw-app-topbar-sidebar.html.twig';
import './sw-app-topbar-sidebar.scss';
import useSidebarStore from 'shopware:stores/sidebar';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        sidebars() {
            return useSidebarStore().sidebars;
        },

        hasActiveSidebar() {
            return useSidebarStore().getActiveSidebar !== null;
        },
    },

    methods: {
        setActiveSidebar(locationId: string) {
            useSidebarStore().setActiveSidebar(locationId);
        },

        toggleSidebar(locationId: string) {
            useSidebarStore().toggleSidebar(locationId);
        },

        // The sidebar returns focus to the button on close — only keyboard focus may show the tooltip
        showTooltipOnKeyboardFocus(event: FocusEvent, showTooltip: () => void) {
            if ((event.target as HTMLElement).matches(':focus-visible')) {
                showTooltip();
            }
        },
    },
};
