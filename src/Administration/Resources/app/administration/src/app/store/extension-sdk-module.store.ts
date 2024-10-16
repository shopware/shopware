/**
<<<<<<< HEAD:src/Administration/Resources/app/administration/src/app/state/extension-sdk-module.store.ts
 * @sw-package framework
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
=======
 * @package admin
 * @private
>>>>>>> da0a4b2d01 (NEXT-38625 - Transition extension sdk module vuex store to pinia store):src/Administration/Resources/app/administration/src/app/store/extension-sdk-module.store.ts
 */
import type { smartBarButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/main-module/';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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

const extensionSdkModules = Shopware.Store.register({
    id: 'extensionSdkModules',

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

            const id = Shopware.Utils.format.md5(JSON.stringify(staticElements));

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
 * @private
 */
export type ExtensionSdkModules = ReturnType<typeof extensionSdkModules>;

/**
 * @private
 */
export default extensionSdkModules;
