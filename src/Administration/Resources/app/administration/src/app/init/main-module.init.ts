/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initMainModules(): void {
    Shopware.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(Shopware.State.get('extensions'))
            .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extensionName) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        const extension = Shopware.State.get('extensions')?.[extensionName];

        await Shopware.State.dispatch('extensionSdkModules/addModule', {
            heading: mainModuleConfig.heading,
            locationId: mainModuleConfig.locationId,
            displaySearchBar: mainModuleConfig.displaySearchBar ?? true,
            baseUrl: extension.baseUrl,
        }).then((moduleId) => {
            if (typeof moduleId !== 'string') {
                return;
            }

            Shopware.State.commit('extensionMainModules/addMainModule', {
                extensionName,
                moduleId,
            });
        });
    });

    Shopware.ExtensionAPI.handle('smartBarButtonAdd', (configuration) => {
        Shopware.State.commit('extensionSdkModules/addSmartBarButton', configuration);
    });
}
