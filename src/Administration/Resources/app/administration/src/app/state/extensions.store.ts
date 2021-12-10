import Vue from 'vue';
import type { Module } from 'vuex';

interface ExtensionsState {
    [extensionName: string]: {
        baseUrl: string
    }
}

const ExtensionsStore: Module<ExtensionsState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionsState => ({}),

    mutations: {
        addExtension(state, { name, baseUrl }: { name: string, baseUrl: string }) {
            if (!state[name]) {
                Vue.set(state, name, {});
            }

            Vue.set(state[name], 'baseUrl', baseUrl);
        },
    },

    getters: {
        allBaseUrls: state => {
            return Object.values(state).map(extension => {
                return extension.baseUrl;
            });
        },
    },
};

export default ExtensionsStore;
export type { ExtensionsState };
