/**
 * @sw-package framework
 */
import { defineStoreOptions, getAcl, getStore, type privileges, setExtensions, type StoreOf } from '../dependencies';

/**
 * @public
 */
export interface Extension {
    name: string;
    baseUrl: string;
    permissions: privileges;
    version?: string;
    type: 'app' | 'plugin';
    sourceType?: string;
    integrationId?: EntityKey<'integration'>;
    active?: boolean;
}

/**
 * @public
 */
export interface ExtensionsState {
    [key: string]: Extension;
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionsStoreId = 'extensions';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const extensionsStoreOptions = defineStoreOptions({
    id: extensionsStoreId,

    state: (): { extensionsState: ExtensionsState } => ({
        extensionsState: {},
    }),

    actions: {
        addExtension({ name, baseUrl, permissions, version, type, sourceType, integrationId, active }: Extension) {
            if (!this.extensionsState[name]) {
                this.extensionsState[name] = {
                    name,
                    baseUrl,
                    permissions,
                    version,
                    type,
                    sourceType,
                    integrationId,
                    active,
                };
            }

            setExtensions(this.extensionsState);
        },
    },

    getters: {
        privilegedExtensionBaseUrls(state) {
            const acl = getAcl();
            const privilegedForAllApps = acl.can('app.all');
            const privilegedBaseUrls: string[] = [];

            Object.keys(state.extensionsState).forEach((extensionName) => {
                const extension = state.extensionsState[extensionName];

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

        privilegedExtensions(state) {
            const acl = getAcl();
            const privilegedForAllApps = acl.can('app.all');
            const privelegedExtensions: Extension[] = [];

            Object.keys(state.extensionsState).forEach((extensionName) => {
                const extension = state.extensionsState[extensionName];

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
});

/**
 * @public
 */
export type Extensions = StoreOf<typeof extensionsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useExtensionsStore = (): Extensions => getStore(extensionsStoreId);
