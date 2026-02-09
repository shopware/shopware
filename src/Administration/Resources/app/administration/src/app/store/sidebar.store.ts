/**
 * @sw-package framework
 */

import type { uiSidebarAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/sidebar';
import { computed, reactive } from 'vue';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type SidebarItemEntry = Omit<uiSidebarAdd, 'responseType'> & {
    baseUrl: string;
    active: boolean;
};

const sidebarsStore = Shopware.Store.register('sidebar', () => {
    const sidebarsOrdered = useExtensionOrderedArray<SidebarItemEntry>();
    const sidebars = sidebarsOrdered.items;

    const addSidebar = ({ locationId, title, icon, resizable, baseUrl }: SidebarItemEntry) => {
        const sidebar = reactive({
            title,
            icon,
            locationId,
            baseUrl,
            resizable,
            active: false,
        });

        sidebarsOrdered.push(sidebar as SidebarItemEntry);
    };

    const closeSidebar = (locationId: string): void => {
        const sidebars = sidebarsOrdered.items.value;
        const sidebar = sidebars.find((item) => item.locationId === locationId);

        if (!sidebar) {
            return;
        }
        sidebar.active = false;
    };

    const removeSidebar = (locationId: string): void => {
        sidebarsOrdered.removeFirstWhere((sidebar) => sidebar.locationId === locationId);
    };

    const setActiveSidebar = (locationId: string): void => {
        const sidebars = sidebarsOrdered.items.value;

        sidebars.forEach((sidebar) => {
            sidebar.active = false;
        });

        const sidebar = sidebars.find((item) => item.locationId === locationId);
        if (!sidebar) {
            return;
        }

        sidebar.active = true;
    };

    const getActiveSidebar = computed(() => {
        return sidebarsOrdered.items.value.find((sidebar) => sidebar.active) ?? null;
    });

    return {
        sidebars,
        getActiveSidebar,
        addSidebar,
        closeSidebar,
        removeSidebar,
        setActiveSidebar,
        reset: sidebarsOrdered.reset,
    };
});

/**
 * @private
 */
export type SidebarStore = ReturnType<typeof sidebarsStore>;

/**
 * @private
 */
export default sidebarsStore;
