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

let pendingCloseTimeout: number | null = null;

function clearPendingClose(): void {
    if (pendingCloseTimeout !== null) {
        window.clearTimeout(pendingCloseTimeout);
        pendingCloseTimeout = null;
    }
}

const sidebarsStore = Shopware.Store.register({
    id: 'sidebar',

    state: () => ({
        sidebars: [] as SidebarItemEntry[],
        closingSidebar: null as string | null,
        // Lets the renderer swap the content instead of replaying the open animation
        switchedWhileOpen: false,
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

            if (this.closingSidebar === locationId) {
                this.closingSidebar = null;
                clearPendingClose();
            }
        },

        // Play the closing animation, then deactivate once it finishes.
        requestCloseSidebar(locationId: string): void {
            const sidebar = this.sidebars.find((item) => item.locationId === locationId);

            // Only the active sidebar can close, so an inactive one must not cancel a pending close
            if (!sidebar?.active || this.closingSidebar === locationId) {
                return;
            }

            if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
                this.closeSidebar(locationId);
                return;
            }

            clearPendingClose();
            this.closingSidebar = locationId;

            pendingCloseTimeout = window.setTimeout(() => {
                pendingCloseTimeout = null;

                // Skip if it was reopened in the meantime.
                if (this.closingSidebar !== locationId) {
                    return;
                }

                this.closeSidebar(locationId);
            }, CLOSE_ANIMATION_DURATION);
        },

        removeSidebar(locationId: string): void {
            this.sidebars = this.sidebars.filter((sidebar) => {
                return sidebar.locationId !== locationId;
            });

            if (this.closingSidebar === locationId) {
                this.closingSidebar = null;
                clearPendingClose();
            }
        },

        setActiveSidebar(locationId: string): void {
            const sidebar = this.sidebars.find((item) => item.locationId === locationId);
            if (!sidebar) {
                return;
            }

            // Resetting state here would drop switchedWhileOpen and replay the open animation
            if (sidebar.active && this.closingSidebar === null) {
                return;
            }

            // The panel is already open when another sidebar is active and not mid-close.
            this.switchedWhileOpen =
                this.closingSidebar === null && this.sidebars.some((item) => item.active && item.locationId !== locationId);

            this.closingSidebar = null;
            clearPendingClose();

            this.sidebars.forEach((item) => {
                item.active = false;
            });

            sidebar.active = true;
        },

        // Close on a repeated trigger of the active sidebar, open it otherwise.
        toggleSidebar(locationId: string): void {
            const sidebar = this.sidebars.find((item) => item.locationId === locationId);

            if (sidebar?.active && this.closingSidebar !== locationId) {
                this.requestCloseSidebar(locationId);
                return;
            }

            this.setActiveSidebar(locationId);
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
