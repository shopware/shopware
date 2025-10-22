/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeSidebar(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopware.ExtensionAPI.handle('uiSidebarAdd', async (sidebarConfig, { _event_ }) => {
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // create sidebar store
        Shopware.Store.get('sidebar').addSidebar({
            baseUrl: extension.baseUrl,
            active: false,
            ...sidebarConfig,
        });
    });

    Shopware.ExtensionAPI.handle('uiSidebarClose', ({ locationId }) => {
        Shopware.Store.get('sidebar').closeSidebar(locationId);
    });

    Shopware.ExtensionAPI.handle('uiSidebarRemove', ({ locationId }) => {
        Shopware.Store.get('sidebar').removeSidebar(locationId);
    });
}
