import Vue from 'vue';
import type { Module } from 'vuex';
import type { extension } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';
import type { extensions } from '@shopware-ag/admin-extension-sdk/es/channel';

/**
 * @package admin
 * @private
 */
export interface Extension {
    name: string,
    baseUrl: string,
    permissions: extension['permissions'],
    version?: string,
    type: 'app'|'plugin',
    integrationId?: string,
    active?: boolean,
}

/**
 * @package admin
 * @private
 */
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

            Vue.set(state[name], 'name', name);
            Vue.set(state[name], 'baseUrl', baseUrl);
            Vue.set(state[name], 'permissions', permissions);
            Vue.set(state[name], 'version', version);
            Vue.set(state[name], 'type', type);
            Vue.set(state[name], 'integrationId', integrationId);
            Vue.set(state[name], 'active', active);
        },
    },

    getters: {
        privilegedExtensionBaseUrls: state => {
            const acl = Shopware.Service('acl');
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

        privilegedExtensions: state => {
            const acl = Shopware.Service('acl');
            const privilegedForAllApps = acl.can('app.all');
            const privelegedExtensions: Extension[] = [];

            Object.keys(state).forEach((extensionName) => {
                const extension = state[extensionName] as Extension;

                if (!privilegedForAllApps && !acl.can(`app.${extensionName}`)) {
                    return;
                }

                if (extension.hasOwnProperty('active') && extension.active === false) {
                    return;
                }

                privelegedExtensions.push(extension);
            });

            return privelegedExtensions;
        },
    },
};

/**
 * @package admin
 * @private
 */
export default ExtensionsStore;
