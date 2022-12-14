/**
 * @package admin
 */

import type { Module } from 'vuex';
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ShopwareAppsState {
    apps: AppModuleDefinition[],
    selectedIds: string[],
}

const shopwareApps: Module<ShopwareAppsState, VuexRootState> = {
    namespaced: true,

    state() {
        return {
            apps: [],
            selectedIds: [],
        };
    },

    getters: {
        /** @deprecated tag:v6.5.0 use adminMenu.appModuleNavigation instead */
        navigation(state, getters, rootState, rootGetters): $TSFixMe {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return rootGetters['adminMenu/appModuleNavigation'];
        },
    },

    mutations: {
        setApps(state, apps: AppModuleDefinition[]) {
            state.apps = apps;
        },

        setSelectedIds(state, selectedIds: string[]) {
            state.selectedIds = selectedIds;
        },
    },

    actions: {
        /** @deprecated tag:v6.5.0 - Will be removed, use the respective mutations instead */
        setAppModules({ commit }, modules: AppModuleDefinition[]) {
            commit('setApps', modules);
        },

        /** @deprecated tag:v6.5.0 - Will be removed, use the respective mutations instead */
        setSelectedIds({ commit }, selectedIds: string[]) {
            commit('setSelectedIds', selectedIds);
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default shopwareApps;
