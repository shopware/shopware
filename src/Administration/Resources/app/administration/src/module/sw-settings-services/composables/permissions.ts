/**
 * @sw-package framework
 */
import { useShopwareServicesStore } from '../store/shopware-services.store';

/* eslint-disable import/prefer-default-export */
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

    window.location.reload();
}

/**
 * @private
 */
export async function revokePermissions() {
    await Shopware.Service('shopwareServicesService').revokePermissions();

    window.location.reload();
}
/* eslint-enable import/prefer-default-export */
