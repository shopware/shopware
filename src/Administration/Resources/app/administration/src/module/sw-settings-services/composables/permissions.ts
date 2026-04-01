/**
 * @sw-package framework
 */
import { useShopwareServicesStore } from '../store/shopware-services.store';

let reloadFn: () => void = () => window.location.reload();

/**
 * Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26).
 * @private
 */
export function _reloadPage() {
    reloadFn();
}

/**
 * For testing only.
 * @private
 */
export function __setReloadFn(fn: () => void) {
    reloadFn = fn;
}

/**
 * @private
 */
export async function grantPermissions() {
    const shopwareServiceStore = useShopwareServicesStore();
    const currentRevision = shopwareServiceStore.currentRevision?.revision;

    if (!currentRevision) {
        throw new Error('No revision available');
    }

    await Shopware.Service('shopwareServicesService').acceptRevision(currentRevision);

    _reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await Shopware.Service('shopwareServicesService').revokePermissions();

    _reloadPage();
}
