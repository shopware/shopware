import useExtensionsStore from 'shopware:stores/extensions';
import useSidebarStore from 'shopware:stores/sidebar';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeSidebar(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiSidebarAdd', async (sidebarConfig, { _event_ }) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // create sidebar store
        useSidebarStore().addSidebar({
            baseUrl: extension.baseUrl,
            active: false,
            ...sidebarConfig,
        });
    });

    Shopware.ExtensionAPI.handle('uiSidebarClose', ({ locationId }) => {
        // Same close path as the panel's close button, so the animation plays too
        useSidebarStore().requestCloseSidebar(locationId);
    });

    Shopware.ExtensionAPI.handle('uiSidebarSetActive', ({ locationId }: { locationId: string }) => {
        useSidebarStore().setActiveSidebar(locationId);
    });

    Shopware.ExtensionAPI.handle('uiSidebarRemove', ({ locationId }) => {
        useSidebarStore().removeSidebar(locationId);
    });
}
