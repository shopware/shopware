import { Module } from 'vuex';
import { menuItemAdd } from '@shopware-ag/admin-extension-sdk/es/ui/menuItem';

export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'positionId'> & { id: string, baseUrl: string }

interface MenuItemState {
    menuItems: MenuItemEntry[],
}

const MenuItemStore: Module<MenuItemState, VuexRootState> = {
    namespaced: true,

    state: (): MenuItemState => ({
        menuItems: [],
    }),

    mutations: {
        addMenuItem(state, { label, locationId, displaySearchBar, parent, baseUrl }: MenuItemEntry) {
            const staticElements = {
                label,
                locationId,
                displaySearchBar,
                parent,
                baseUrl,
            };

            state.menuItems.push({
                id: Shopware.Utils.format.md5(JSON.stringify(staticElements)),
                ...staticElements,
            });
        },
    },
};

export default MenuItemStore;
export type { MenuItemState };
