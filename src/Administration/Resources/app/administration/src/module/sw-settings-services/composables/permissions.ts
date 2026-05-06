/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import { useShopwareServicesStore } from '../store/shopware-services.store';
import { SERVICE_CONSENT_NAME } from '../store/shopware-services.store';

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

    await useConsentStore().accept(SERVICE_CONSENT_NAME, currentRevision);

    _reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await useConsentStore().revoke(SERVICE_CONSENT_NAME);

    _reloadPage();
}
