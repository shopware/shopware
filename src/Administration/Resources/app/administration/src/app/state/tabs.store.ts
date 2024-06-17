/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
import type { Module } from 'vuex';
import type { uiTabsAddTabItem } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';

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
                state.tabItems[positionId] = [];
            }

            state.tabItems[positionId].push({
                label,
                componentSectionId,
            });
        },
    },
};

/**
 * @private
 */
export default TabsStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { TabsState };
