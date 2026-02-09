/**
 * @sw-package framework
 */

import type { menuItemAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/menu';
import { reactive } from 'vue';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'locationId' | 'displaySearchBar'> & {
    moduleId: string;
};

const menuItemStore = Shopware.Store.register('menuItem', () => {
    const menuItemsOrdered = useExtensionOrderedArray<MenuItemEntry>();
    const menuItems = menuItemsOrdered.items;

    const addMenuItem = ({ label, parent, position, moduleId }: MenuItemEntry) => {
        menuItemsOrdered.push({
            label,
            parent,
            position,
            moduleId,
        });
    };

    return reactive({
        menuItems,
        addMenuItem,
    });
});

/**
 * @private
 */
export type MenuItemStore = ReturnType<typeof menuItemStore>;

/**
 * @private
 */
export default menuItemStore;
