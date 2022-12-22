/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initMainModules(): void {
    Shopware.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(Shopware.State.get('extensions'))
            .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extensionName) {
            return;
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
}
