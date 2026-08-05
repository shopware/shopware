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
export async function grantPermissions({
    reload = true,
}: { reload?: boolean } = {}) {
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

    if (reload) {
        _reloadPage();
    }
}

/**
 * @private
 */
export async function revokePermissions() {
    await Shopware.Service('shopwareServicesService').revokePermissions();

    _reloadPage();
}

/**
 * @private
 */
export const grantPermissionsFromSdk: HandleMethod<'permissionsGrant'> = (_message, { _event_ }) => {
    let isService = false;

    try {
        const appOrigin = _event_.origin;
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) => {
            return ext.baseUrl.startsWith(appOrigin);
        });

        isService = extension?.sourceType === 'service';
    } catch {
        isService = false;
    }

    if (!isService) {
        throw new Error('Only Shopware Services can grant permissions.');
    }

    return grantPermissions({ reload: false });
};
