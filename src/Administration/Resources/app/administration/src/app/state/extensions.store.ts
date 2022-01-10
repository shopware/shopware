import Vue from 'vue';
import type { Module } from 'vuex';
import type { extensions, privileges } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';

const ExtensionsStore: Module<extensions, VuexRootState> = {
    namespaced: true,

    state: (): extensions => ({}),

    mutations: {
        addExtension(state, { name, baseUrl, permissions }: { name: string, baseUrl: string, permissions: privileges }) {
            if (!state[name]) {
                Vue.set(state, name, {});
            }

            Vue.set(state[name], 'baseUrl', baseUrl);
            Vue.set(state[name], 'permissions', permissions);
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
