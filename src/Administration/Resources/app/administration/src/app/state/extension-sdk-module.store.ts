/**
 * @package admin
 */
/* Is covered by E2E tests */
/* istanbul ignore file */
import type { Module } from 'vuex';
import type { smartBarButtonAdd } from '@shopware-ag/admin-extension-sdk/es/ui/mainModule';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ExtensionSdkModule = {
    id: string,
    heading: string,
    baseUrl: string,
    locationId: string,
    displaySearchBar: boolean,
    displayLanguageSwitch: boolean,
};

interface ExtensionSdkModuleState {
    modules: ExtensionSdkModule[],

    smartBarButtons: smartBarButtonAdd[],
}

const ExtensionSdkModuleStore: Module<ExtensionSdkModuleState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionSdkModuleState => ({
        modules: [],
        smartBarButtons: [],
    }),

    actions: {
        addModule(
            { state },
            { heading, locationId, displaySearchBar, displayLanguageSwitch, baseUrl }: ExtensionSdkModule,
        ): Promise<string> {
            const staticElements = {
                heading,
                locationId,
                displaySearchBar,
                displayLanguageSwitch,
                baseUrl,
            };

            const id = Shopware.Utils.format.md5(JSON.stringify(staticElements));

            // Only push the module if it does not exist yet
            if (!state.modules.some(module => module.id === id)) {
                state.modules.push({
                    id,
                    ...staticElements,
                });
            }

            return Promise.resolve(id);
        },
    },

    mutations: {
        addSmartBarButton(state, button: smartBarButtonAdd) {
            state.smartBarButtons.push(button);
        },
    },

    getters: {
        getRegisteredModuleInformation: (state) => (baseUrl: string): ExtensionSdkModule[] => {
            return state.modules.filter((module) => module.baseUrl.startsWith(baseUrl));
        },
    },
};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default ExtensionSdkModuleStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ExtensionSdkModuleState };
