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

// Keep in sync with the close animation duration in sw-sidebar-renderer.scss.
const CLOSE_ANIMATION_DURATION = 400;

const sidebarsStore = Shopware.Store.register({
    id: 'sidebar',

    state: () => ({
        sidebars: [] as SidebarItemEntry[],
        closingSidebar: null as string | null,
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
        addSidebar({ locationId, title, icon, resizable, baseUrl }: SidebarItemEntry) {
            const sidebar = reactive({
                title,
                icon,
                locationId,
                baseUrl,
                resizable,
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

        // Play the closing animation, then deactivate once it finishes.
        requestCloseSidebar(locationId: string): void {
            if (this.closingSidebar === locationId) {
                return;
            }

            this.closingSidebar = locationId;
            window.setTimeout(() => {
                // Skip if it was reopened in the meantime.
                if (this.closingSidebar !== locationId) {
                    return;
                }

                this.closeSidebar(locationId);
                this.closingSidebar = null;
            }, CLOSE_ANIMATION_DURATION);
        },

        removeSidebar(locationId: string): void {
            this.sidebars = this.sidebars.filter((sidebar) => {
                return sidebar.locationId !== locationId;
            });
        },

        // Store API
        setActiveSidebar(locationId: string): void {
            // cancel any pending close animation
            this.closingSidebar = null;

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
