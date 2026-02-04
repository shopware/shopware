/**
 * @sw-package framework
 * @private
 */
import type { smartBarButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/main-module/';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

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

const extensionSdkModules = Shopware.Store.register('extensionSdkModules', () => {
    const modulesOrdered = useExtensionOrderedArray<ExtensionSdkModule>();
    const smartBarButtonsOrdered = useExtensionOrderedArray<Omit<smartBarButtonAdd, 'responseType'>>();
    const hiddenSmartBarsOrdered = useExtensionOrderedArray<string>();

    const addModule = ({
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
    }): Promise<string> => {
        const staticElements = {
            heading,
            locationId,
            displaySearchBar,
            displaySmartBar,
            displayLanguageSwitch,
            baseUrl,
        };

        const id = Shopware.Utils.format.md5(JSON.stringify(staticElements));
        const modules = modulesOrdered.items.value;

        if (!modules.some((module) => module.id === id)) {
            modulesOrdered.push({
                id,
                ...staticElements,
            } as ExtensionSdkModule);
        }

        return Promise.resolve(id);
    };

    const addSmartBarButton = (button: Omit<smartBarButtonAdd, 'responseType'>) => {
        smartBarButtonsOrdered.push(button);
    };

    const addHiddenSmartBar = (locationId: string) => {
        hiddenSmartBarsOrdered.push(locationId);
    };

    const getRegisteredModuleInformation = (baseUrl: string): ExtensionSdkModule[] => {
        return modulesOrdered.items.value.filter((module) => module.baseUrl.startsWith(baseUrl));
    };

    const flushByCurrentExtension = () => {
        modulesOrdered.flushByCurrentExtension();
        smartBarButtonsOrdered.flushByCurrentExtension();
        hiddenSmartBarsOrdered.flushByCurrentExtension();
    };

    return {
        modules: modulesOrdered.items,
        smartBarButtons: smartBarButtonsOrdered.items,
        hiddenSmartBars: hiddenSmartBarsOrdered.items,
        addModule,
        addSmartBarButton,
        addHiddenSmartBar,
        getRegisteredModuleInformation,
        flushByCurrentExtension,
    };
});

/**
 * @private
 */
export type ExtensionSdkModules = ReturnType<typeof extensionSdkModules>;

/**
 * @private
 */
export default extensionSdkModules;
