import Vue, { reactive } from 'vue';
// @ts-expect-error - compatUtils is not typed
import { compatUtils } from '@vue/compat';
import type { Module } from 'vuex';
import type { extension } from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges';
import type { extensions } from '@shopware-ag/meteor-admin-sdk/es/channel';
import { setExtensions } from '@shopware-ag/meteor-admin-sdk/es/channel';

/**
 * @package admin
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia implementation
 */
export interface Extension {
    name: string;
    baseUrl: string;
    permissions: extension['permissions'];
    version?: string;
    type: 'app' | 'plugin';
    integrationId?: string;
    active?: boolean;
}

/**
 * @package admin
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia implementation
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
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                if (compatUtils.isCompatEnabled('GLOBAL_SET')) {
                    Vue.set(state, name, {});
                    Vue.set(state[name], 'name', name);
                    Vue.set(state[name], 'baseUrl', baseUrl);
                    Vue.set(state[name], 'permissions', permissions);
                    Vue.set(state[name], 'version', version);
                    Vue.set(state[name], 'type', type);
                    Vue.set(state[name], 'integrationId', integrationId);
                    Vue.set(state[name], 'active', active);
                } else {
                    // @ts-expect-error
                    state[name] = reactive({});

                    // Somehow the type here is broken and not fixable not even with a type predicate function
                    (state[name] as Extension).name = name;
                    (state[name] as Extension).baseUrl = baseUrl;
                    (state[name] as Extension).permissions = permissions;
                    (state[name] as Extension).version = version;
                    (state[name] as Extension).type = type;
                    (state[name] as Extension).integrationId = integrationId;
                    (state[name] as Extension).active = active;
                }
            }

            setExtensions(state);
        },
    },

    getters: {
        privilegedExtensionBaseUrls: (state) => {
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

        privilegedExtensions: (state) => {
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
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
export default ExtensionsStore;
