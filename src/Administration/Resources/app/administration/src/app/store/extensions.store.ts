/**
 * @sw-package framework
 * @private
 */
import { setExtensions } from '@shopware-ag/meteor-admin-sdk/es/channel';

/**
 * @private
 */
export interface Extension {
    name: string;
    baseUrl: string;
    permissions: Record<string, unknown>;
    version?: string;
    type: 'app' | 'plugin';
    integrationId?: string;
    active?: boolean;
}

/**
 * @private
 */
export interface ExtensionsState {
    [key: string]: Extension;
}

const extensions = Shopware.Store.register({
    id: 'extensions',

    state: (): { extensionsState: ExtensionsState } => ({
        extensionsState: {},
    }),

    actions: {
        addExtension({ name, baseUrl, permissions, version, type, integrationId, active }: Extension) {
            if (!this.extensionsState[name]) {
                this.extensionsState[name] = {
                    name,
                    baseUrl,
                    permissions,
                    version,
                    type,
                    integrationId,
                    active,
                };
            }

            setExtensions(this.extensionsState);
        },
    },

    getters: {
        privilegedExtensionBaseUrls(state) {
            const acl = Shopware.Service('acl');
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
            const acl = Shopware.Service('acl');
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
 * @private
 */
export type Extensions = ReturnType<typeof extensions>;

/**
 * @private
 */
export default extensions;
