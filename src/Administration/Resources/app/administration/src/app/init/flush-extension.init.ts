/**
 * @sw-package framework
 *
 * @private
 * @description Handles extension flush events before HMR reload.
 * This allows extensions to clean up their registered state (tabs, menu items,
 * component sections, etc.) before HMR re-registers them.
 */

interface FlushExtensionData {
    extensionName: string;
    extensionType: 'plugin' | 'app';
    timestamp: number;
}

function handleFlushExtension(origin: string, data: FlushExtensionData): void {
    console.debug(
        `[FlushExtension] Flushing ${data.extensionType || 'extension'} "${data.extensionName}" before HMR reload`);

    Shopware.Store.get('tabs').flushByCurrentExtension();
    Shopware.Store.get('menuItem').flushByCurrentExtension();
    Shopware.Store.get('settingsItems').flushByCurrentExtension();
    Shopware.Store.get('actionButtons').flushByCurrentExtension();
    Shopware.Store.get('extensionMainModules').flushByCurrentExtension();
    Shopware.Store.get('sidebar').flushByCurrentExtension();
    Shopware.Store.get('topBarButton').flushByCurrentExtension();
    Shopware.Store.get('extensionComponentSections').flushByCurrentExtension();
    Shopware.Store.get('extensionSdkModules').flushByCurrentExtension();
    Shopware.Store.get('modals').flushByCurrentExtension();
    Shopware.Store.get('extensionEntryRoutes').removeByExtensionName(data.extensionName);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeFlushExtension(): void {
    // Also register with ExtensionAPI for SDK-based sends (if SDK is used)
    // @ts-expect-error - Custom message type for HMR flush
    Shopware.ExtensionAPI.handle('__flushExtension', (data: FlushExtensionData, additionalInformation) => {
        handleFlushExtension(additionalInformation._event_.origin, data);
    });
}
