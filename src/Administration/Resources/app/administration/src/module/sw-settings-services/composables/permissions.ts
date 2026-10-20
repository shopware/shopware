/**
 * @sw-package framework
 */
import type { HandleMethod } from '@shopware-ag/meteor-admin-sdk/es/channel';
import useSession from 'src/app/composables/use-session';
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
    let currentRevision = shopwareServiceStore.currentRevision?.revision;

    if (!currentRevision) {
        const sessionStore = useSession();
        const revisionData = await Shopware.Service('serviceRegistryClient').getCurrentRevision(
            sessionStore.currentLocale.value ?? 'en-GB',
        );

        shopwareServiceStore.revisions = revisionData;
        currentRevision = shopwareServiceStore.currentRevision?.revision;
    }

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

    if (!shopwareServicesStore.revisions) {
        const locale = useSession().currentLocale.value ?? 'en-GB';
        shopwareServicesStore.revisions = await Shopware.Service('serviceRegistryClient').getCurrentRevision(locale);
    }

    return shopwareServicesStore.consentGiven;
};
