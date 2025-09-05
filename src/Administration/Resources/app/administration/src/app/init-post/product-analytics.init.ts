/**
 * @sw-package framework
 * @private
 */
export default function initializeTracking(): Promise<void> {
    Shopware.Telemetry.initialize();

    return Promise.resolve();
}
