/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeActions(): void {
    Shopware.ExtensionAPI.handle('actionExecute', async (actionConfiguration, additionalInformation) => {
        const extensionName = Object.keys(Shopware.State.get('extensions'))
            .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extensionName) {
            // eslint-disable-next-line max-len
            throw new Error(`Could not find an extension with the given event origin "${additionalInformation._event_.origin}"`);
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
