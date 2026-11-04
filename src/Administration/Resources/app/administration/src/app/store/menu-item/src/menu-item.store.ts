/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type menuItemAdd, type StoreOf } from '../dependencies';

/**
 * @public
 */
export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'locationId' | 'displaySearchBar'> & {
    moduleId: string;
};

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const menuItemStoreId = 'menuItem';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const menuItemStoreOptions = defineStoreOptions({
    id: menuItemStoreId,

    state: () => ({
        menuItems: [] as MenuItemEntry[],
    }),

    actions: {
        addMenuItem({ label, parent, position, moduleId }: MenuItemEntry) {
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
 * @public
 */
export type MenuItemStore = StoreOf<typeof menuItemStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useMenuItemStore = (): MenuItemStore => getStore(menuItemStoreId);
