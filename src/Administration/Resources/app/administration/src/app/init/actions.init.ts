/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeActions(): void {
    Shopware.ExtensionAPI.handle('actionExecute', async (actionConfiguration, additionalInformation) => {
        const extensionName = Object.keys(Shopware.Store.get('extensions').extensionsState).find((key) =>
            Shopware.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            // eslint-disable-next-line max-len
            throw new Error(
                `Could not find an extension with the given event origin "${additionalInformation._event_.origin}"`,
            );
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
