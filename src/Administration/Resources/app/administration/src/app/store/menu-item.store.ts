/**
 * @sw-package framework
 */

import type { menuItemAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/menu';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'locationId' | 'displaySearchBar'> & {
    moduleId: string;
};

const menuItemStore = Shopware.Store.register({
    id: 'menuItem',

    state: () => ({
        menuItems: [] as MenuItemEntry[],
    }),

    actions: {
        addMenuItem({ label, parent, position, moduleId }: MenuItemEntry) {
            // Check if menu item with same moduleId already exists to prevent duplicates on HMR
            const existingIndex = this.menuItems.findIndex((item) => item.moduleId === moduleId);

            if (existingIndex !== -1) {
                // Update existing menu item
                this.menuItems[existingIndex] = { label, parent, position, moduleId };
                return;
            }

            this.menuItems.push({
                label,
                parent,
                position,
                moduleId,
            });
        },
    },
});

/**
 * @private
 */
export type MenuItemStore = ReturnType<typeof menuItemStore>;

/**
 * @private
 */
export default menuItemStore;
