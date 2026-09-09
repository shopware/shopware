import { createId } from 'shopware:utils';
import useExtensionsStore from 'shopware:stores/extensions';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeActions(): void {
    Shopware.ExtensionAPI.handle('actionExecute', async (actionConfiguration, additionalInformation) => {
        const extensionName = Object.keys(useExtensionsStore().extensionsState).find((key) =>
            useExtensionsStore().extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(
                `Could not find an extension with the given event origin "${additionalInformation._event_.origin}"`,
            );
        }

        await Shopware.Service('extensionSdkService').runAction(
            {
                url: actionConfiguration.url,
                entity: actionConfiguration.entity,
                action: createId(),
                appName: extensionName,
            },
            actionConfiguration.entityIds,
        );
    });
}
