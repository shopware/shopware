import template from './sw-app-topbar-sidebar.html.twig';
import './sw-app-topbar-sidebar.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        sidebars() {
            return Shopware.Store.get('sidebar').sidebars;
        },

        hasActiveSidebar() {
            return Shopware.Store.get('sidebar').getActiveSidebar !== null;
        },
    },

    methods: {
        setActiveSidebar(locationId: string) {
            Shopware.Store.get('sidebar').setActiveSidebar(locationId);
        },

        toggleSidebar(locationId: string) {
            Shopware.Store.get('sidebar').toggleSidebar(locationId);
        },

        // The sidebar returns focus to the button on close — only keyboard focus may show the tooltip
        showTooltipOnKeyboardFocus(event: FocusEvent, showTooltip: () => void) {
            if ((event.target as HTMLElement).matches(':focus-visible')) {
                showTooltip();
            }
        },
    },
};
