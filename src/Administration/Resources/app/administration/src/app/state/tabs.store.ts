import Vue from 'vue';
import type { Module } from 'vuex';
import type { uiTabsAddTabItem } from '@shopware-ag/admin-extension-sdk/es/ui/tabs';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TabItemEntry = Omit<uiTabsAddTabItem, 'responseType' | 'positionId'>;

interface TabsState {
    tabItems: {
        [positionId: string]: TabItemEntry[]
    }
}

const TabsStore: Module<TabsState, VuexRootState> = {
    namespaced: true,

    state: (): TabsState => ({
        tabItems: {},
    }),

    mutations: {
        addTabItem(state, { label, componentSectionId, positionId }: uiTabsAddTabItem) {
            if (!state.tabItems[positionId]) {
                Vue.set(state.tabItems, positionId, []);
            }

            state.tabItems[positionId].push({
                label,
                componentSectionId,
            });
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default TabsStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { TabsState };
