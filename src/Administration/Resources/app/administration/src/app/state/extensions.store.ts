import Vue from 'vue';
import type { Module } from 'vuex';
import type { extensions, extension } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';

export interface Extension {
    name: string,
    baseUrl: string,
    permissions: extension['permissions'],
    version?: string,
    type: 'app'|'plugin',
}

export interface ExtensionsState {
    [key: string]: Extension;
}

const ExtensionsStore: Module<extensions, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionsState => ({}),

    mutations: {
        addExtension(state, { name, baseUrl, permissions, version, type }: Extension) {
            if (!state[name]) {
                Vue.set(state, name, {});
            }

            Vue.set(state[name], 'baseUrl', baseUrl);
            Vue.set(state[name], 'permissions', permissions);
            Vue.set(state[name], 'version', version);
            Vue.set(state[name], 'type', type);
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
