/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
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
    const consentStore = useConsentStore();
    // The latest revision the merchant is consenting to is the authoritative one carried on the
    // consent itself (sourced from the registry server-side) — no need for a separate revision fetch.
    const latestRevision = consentStore.consents[SERVICE_CONSENT_NAME]?.latestRevision ?? null;

    await consentStore.accept(SERVICE_CONSENT_NAME, latestRevision);

    _reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await useConsentStore().revoke(SERVICE_CONSENT_NAME);

    _reloadPage();
}
