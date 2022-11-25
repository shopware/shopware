/* Is covered by E2E tests */
/* istanbul ignore file */
import type { Module } from 'vuex';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ExtensionSdkModule = {
    id: string,
    heading: string,
    baseUrl: string,
    locationId: string,
    displaySearchBar: boolean,
};

interface ExtensionSdkModuleState {
    modules: ExtensionSdkModule[],
}

const ExtensionSdkModuleStore: Module<ExtensionSdkModuleState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionSdkModuleState => ({
        modules: [],
    }),

    actions: {
        addModule({ state }, { heading, locationId, displaySearchBar, baseUrl }: ExtensionSdkModule): Promise<string> {
            const staticElements = {
                heading,
                locationId,
                displaySearchBar,
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

    getters: {
        getRegisteredModuleInformation: (state) => (baseUrl: string): ExtensionSdkModule[] => {
            return state.modules.filter((module) => module.baseUrl.startsWith(baseUrl));
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ExtensionSdkModuleStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ExtensionSdkModuleState };
