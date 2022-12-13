/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActions(): void {
    Shopware.ExtensionAPI.handle('actionExecute', async (actionConfiguration, additionalInformation) => {
        const extensionName = Object.keys(Shopware.State.get('extensions'))
            .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extensionName) {
            return;
        }

        await Shopware.Service('extensionSdkService').runAction(
            {
                url: actionConfiguration.url,
                entity: actionConfiguration.entity,
                action: Shopware.Utils.createId(),
                appName: extensionName,
            },
            actionConfiguration.entityIds,
        );
    });
}
