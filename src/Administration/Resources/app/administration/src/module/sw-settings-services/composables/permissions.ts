/**
 * @sw-package framework
 */
import { reloadPage } from 'src/core/helper/navigation.helper';
import { useShopwareServicesStore } from '../store/shopware-services.store';

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

    reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await Shopware.Service('shopwareServicesService').revokePermissions();

    reloadPage();
}
