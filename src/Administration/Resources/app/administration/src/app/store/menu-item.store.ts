/**
 * @sw-package framework
 */

import type { menuItemAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/menu';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'locationId' | 'displaySearchBar'> & {
    moduleId: string;
};

const menuItemStore = Shopware.Store.register('menuItem', () => {
    const menuItemsOrdered = useExtensionOrderedArray<MenuItemEntry>();

    const addMenuItem = ({ label, parent, position, moduleId }: MenuItemEntry) => {
        menuItemsOrdered.push({
            label,
            parent,
            position,
            moduleId,
        });
    };

    return {
        menuItems: menuItemsOrdered.items,
        addMenuItem,
        flushByCurrentExtension: menuItemsOrdered.flushByCurrentExtension,
    };
});

/**
 * @private
 */
export type MenuItemStore = ReturnType<typeof menuItemStore>;

/**
 * @private
 */
export default menuItemStore;
