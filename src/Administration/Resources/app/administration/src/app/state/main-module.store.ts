/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import type { Module } from 'vuex';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MainModule = {
    extensionName: string;
    moduleId: string;
};

interface MainModuleState {
    mainModules: MainModule[];
}

const MainModuleStore: Module<MainModuleState, VuexRootState> = {
    namespaced: true,

    state: (): MainModuleState => ({
        mainModules: [],
    }),

    mutations: {
        addMainModule(state, { extensionName, moduleId }: MainModule) {
            state.mainModules.push({
                extensionName,
                moduleId,
            });
        },
    },
};

/**
 * @private
 */
export default MainModuleStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { MainModuleState };
