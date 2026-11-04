/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, md5, type smartBarButtonAdd, type StoreOf } from '../dependencies';

/**
 * @public
 */
export type ExtensionSdkModule = {
    id: string;
    heading: string;
    baseUrl: string;
    locationId: string;
    displaySearchBar: boolean;
    displaySmartBar: boolean;
    displayLanguageSwitch: boolean;
};

interface ExtensionSdkModuleState {
    modules: ExtensionSdkModule[];

    smartBarButtons: Omit<smartBarButtonAdd, 'responseType'>[];

    hiddenSmartBars: string[];
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionSdkModulesStoreId = 'extensionSdkModules';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const extensionSdkModulesStoreOptions = defineStoreOptions({
    id: extensionSdkModulesStoreId,

    state: (): ExtensionSdkModuleState => ({
        modules: [],
        smartBarButtons: [],
        hiddenSmartBars: [],
    }),

    actions: {
        addModule({
            heading,
            locationId,
            displaySearchBar,
            displaySmartBar,
            displayLanguageSwitch,
            baseUrl,
        }: {
            heading: ExtensionSdkModule['heading'];
            locationId: ExtensionSdkModule['locationId'];
            displaySearchBar: ExtensionSdkModule['displaySearchBar'];
            displaySmartBar?: ExtensionSdkModule['displaySmartBar'];
            displayLanguageSwitch?: ExtensionSdkModule['displayLanguageSwitch'];
            baseUrl: ExtensionSdkModule['baseUrl'];
        }): Promise<string> {
            const staticElements = {
                heading,
                locationId,
                displaySearchBar,
                displaySmartBar,
                displayLanguageSwitch,
                baseUrl,
            };

            const id = md5(JSON.stringify(staticElements));

            // Only push the module if it does not exist yet
            if (!this.modules.some((module) => module.id === id)) {
                this.modules.push({
                    id,
                    ...staticElements,
                } as ExtensionSdkModule);
            }

            return Promise.resolve(id);
        },

        addSmartBarButton(button: Omit<smartBarButtonAdd, 'responseType'>) {
            this.smartBarButtons.push(button);
        },

        addHiddenSmartBar(locationId: string) {
            this.hiddenSmartBars.push(locationId);
        },
    },

    getters: {
        getRegisteredModuleInformation:
            (state) =>
            (baseUrl: string): ExtensionSdkModule[] => {
                return state.modules.filter((module) => module.baseUrl.startsWith(baseUrl));
            },
    },
});

/**
 * @public
 */
export type ExtensionSdkModules = StoreOf<typeof extensionSdkModulesStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useExtensionSdkModulesStore = (): ExtensionSdkModules => getStore(extensionSdkModulesStoreId);
