/**
 * @package admin
 */

import type { Module } from 'vuex';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MainModule = {
    extensionName: string,
    moduleId: string,
};

interface MainModuleState {
    mainModules: MainModule[]
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
 * @deprecated tag:v6.6.0 - Will be private
 */
export default MainModuleStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { MainModuleState };
