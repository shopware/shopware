/**
 * @sw-package framework
 */
import type { HandleMethod } from '@shopware-ag/meteor-admin-sdk/es/channel';
import useConsentStore from 'src/core/consent/consent.store';
import { useShopwareServicesStore, SERVICE_CONSENT_NAME } from '../store/shopware-services.store';

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

function assertServiceOrigin(origin: string): void {
    const matchingExtensions = Object.values(Shopware.Store.get('extensions').extensionsState).filter((extension) => {
        try {
            return new URL(extension.baseUrl).origin === origin;
        } catch {
            return false;
        }
    });

    if (matchingExtensions.length === 0 || !matchingExtensions.every((extension) => extension.sourceType === 'service')) {
        throw new Error('Only Shopware Services can access this handler.');
    }
}

/**
 * @private
 */
export const grantPermissionsFromSdk: HandleMethod<'servicePermissionGrant'> = (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    return grantPermissions();
};

/**
 * Resolves to `true` when the Shopware Services consent is already granted or not needed,
 * i.e. the latest revision has been consented to, or Shopware Services are disabled.
 *
 * @private
 */
export const isPermissionGrantedFromSdk: HandleMethod<'servicePermissionIsGranted'> = async (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    const shopwareServicesStore = useShopwareServicesStore();

    if (!shopwareServicesStore.config) {
        shopwareServicesStore.config = await Shopware.Service('shopwareServicesService').getServicesContext();
    }

    if (shopwareServicesStore.config?.disabled) {
        return true;
    }

    return useConsentStore().isAccepted(SERVICE_CONSENT_NAME);
};
