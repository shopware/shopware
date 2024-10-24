/**
 * @sw-package framework
 *
 * @private
 */
export default function initMainModules(): void {
    Shopware.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(Shopware.Store.get('extensions').extensionsState).find((key) =>
            Shopware.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        const extension = Shopware.Store.get('extensions').extensionsState?.[extensionName];

        await Shopware.Store.get('extensionSdkModules')
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

                Shopware.Store.get('extensionMainModules').addMainModule({
                    extensionName,
                    moduleId,
                });
            });
    });

    Shopware.ExtensionAPI.handle('smartBarButtonAdd', (configuration) => {
        Shopware.Store.get('extensionSdkModules').addSmartBarButton(configuration);
    });

    Shopware.ExtensionAPI.handle('smartBarHide', (configuration) => {
        Shopware.Store.get('extensionSdkModules').addHiddenSmartBar(configuration.locationId);
    });
}
