/**
 * @sw-package framework
 */

import type { uiSidebarAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/sidebar';
import { reactive } from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type SidebarItemEntry = Omit<uiSidebarAdd, 'responseType'> & {
    baseUrl: string;
    active: boolean;
};

const sidebarsStore = Shopware.Store.register({
    id: 'sidebar',

    state: () => ({
        sidebars: [] as SidebarItemEntry[],
    }),

    getters: {
        getActiveSidebar(): SidebarItemEntry | null {
            return (
                this.sidebars.find((sidebar) => {
                    return sidebar.active;
                }) || null
            );
        },
    },

    actions: {
        // Extension API message methods
        addSidebar({ locationId, title, icon, baseUrl }: SidebarItemEntry) {
            const sidebar = reactive({
                title,
                icon,
                locationId,
                baseUrl,
                active: false,
            });

            this.sidebars.push(sidebar);
        },

        closeSidebar(locationId: string): void {
            const sidebar = this.sidebars.find((item) => {
                return item.locationId === locationId;
            });

            if (!sidebar) {
                return;
            }
            sidebar.active = false;
        },

        removeSidebar(locationId: string): void {
            this.sidebars = this.sidebars.filter((sidebar) => {
                return sidebar.locationId !== locationId;
            });
        },

        // Store API
        setActiveSidebar(locationId: string): void {
            // reset all sidebars
            this.sidebars.forEach((sidebar) => {
                sidebar.active = false;
            });

            const sidebar = this.sidebars.find((item) => item.locationId === locationId);
            if (!sidebar) {
                return;
            }

            sidebar.active = true;
        },
    },
});

/**
 * @private
 */
export type SidebarStore = ReturnType<typeof sidebarsStore>;

/**
 * @private
 */
export default sidebarsStore;
