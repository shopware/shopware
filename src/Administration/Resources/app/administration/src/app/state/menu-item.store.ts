import { Module } from 'vuex';
import { menuItemAdd } from '@shopware-ag/admin-extension-sdk/es/ui/menu';

export type MenuItemEntry = Omit<menuItemAdd, 'responseType' | 'locationId' | 'displaySearchBar'> & { moduleId: string }

interface MenuItemState {
    menuItems: MenuItemEntry[],
}

const MenuItemStore: Module<MenuItemState, VuexRootState> = {
    namespaced: true,

    state: (): MenuItemState => ({
        menuItems: [],
    }),

    mutations: {
        addMenuItem(state, { label, parent, position, moduleId }: MenuItemEntry) {
            state.menuItems.push({
                label,
                parent,
                position,
                moduleId,
            });
        },
    },
};

export default MenuItemStore;
export type { MenuItemState };
