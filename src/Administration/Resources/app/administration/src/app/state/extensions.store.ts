import Vue from 'vue';
import type { Module } from 'vuex';
import type { extension } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';
import type { extensions } from '@shopware-ag/admin-extension-sdk/es/channel';
import type AclService from '../service/acl.service';

export interface Extension {
    name: string,
    baseUrl: string,
    permissions: extension['permissions'],
    version?: string,
    type: 'app'|'plugin',
    integrationId?: string,
    active?: boolean,
}

export interface ExtensionsState {
    [key: string]: Extension;
}

const ExtensionsStore: Module<extensions, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionsState => ({}),

    mutations: {
        addExtension(state, { name, baseUrl, permissions, version, type, integrationId, active }: Extension) {
            if (!state[name]) {
                Vue.set(state, name, {});
            }

            Vue.set(state[name], 'baseUrl', baseUrl);
            Vue.set(state[name], 'permissions', permissions);
            Vue.set(state[name], 'version', version);
            Vue.set(state[name], 'type', type);
            Vue.set(state[name], 'integrationId', integrationId);
            Vue.set(state[name], 'active', active);
        },
    },

    getters: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed use allActiveBaseUrls instead.
         */
        allBaseUrls: state => {
            return Object.values(state).map(extension => {
                return extension.baseUrl;
            });
        },

        privilegedExtensionBaseUrls: state => {
            const acl = Shopware.Service('acl') as AclService;
            const privilegedForAllApps = acl.can('app.all');
            const privilegedBaseUrls: string[] = [];

            Object.keys(state).forEach((extensionName) => {
                const extension = state[extensionName] as Extension;

                if (!privilegedForAllApps && !acl.can(`app.${extensionName}`)) {
                    return;
                }

                if (extension.hasOwnProperty('active') && extension.active === false) {
                    return;
                }

                privilegedBaseUrls.push(extension.baseUrl);
            });

            return privilegedBaseUrls;
        },
    },
};

export default ExtensionsStore;
