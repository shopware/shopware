import useExtensionMainModulesStore from 'shopware:stores/extensionMainModules';
import useExtensionsStore from 'shopware:stores/extensions';
import useExtensionSdkModulesStore from 'shopware:stores/extensionSdkModules';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initMainModules(): void {
    Shopware.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(useExtensionsStore().extensionsState).find((key) =>
            useExtensionsStore().extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        const extension = useExtensionsStore().extensionsState?.[extensionName];

        await useExtensionSdkModulesStore()
            .addModule({
                heading: mainModuleConfig.heading,
                locationId: mainModuleConfig.locationId,
                displaySearchBar: mainModuleConfig.displaySearchBar ?? true,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                useExtensionMainModulesStore().addMainModule({
                    extensionName,
                    moduleId,
                });
            });
    });

    Shopware.ExtensionAPI.handle('smartBarButtonAdd', (configuration) => {
        useExtensionSdkModulesStore().addSmartBarButton(configuration);
    });

    Shopware.ExtensionAPI.handle('smartBarHide', (configuration) => {
        useExtensionSdkModulesStore().addHiddenSmartBar(configuration.locationId);
    });
}
