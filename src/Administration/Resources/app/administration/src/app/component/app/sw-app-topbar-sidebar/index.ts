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
            return Shopware.Store.get('sidebar').sidebars.some((sidebar) => sidebar.active);
        },
    },

    methods: {
        setActiveSidebar(locationId: string) {
            Shopware.Store.get('sidebar').setActiveSidebar(locationId);
        },

        toggleSidebar(locationId: string) {
            const store = Shopware.Store.get('sidebar');
            const sidebar = store.sidebars.find((item) => item.locationId === locationId);

            if (sidebar?.active && store.closingSidebar !== locationId) {
                store.requestCloseSidebar(locationId);
                return;
            }

            store.setActiveSidebar(locationId);
        },
    },
};
