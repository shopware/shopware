import { Module } from 'vuex';

export type MainModule = {
    extensionName: string,
    moduleId: string,
}

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

export default MainModuleStore;
export type { MainModuleState };
