/**
 * @sw-package framework:fundamentals
 */
import useConsentStore from 'src/core/consent/consent.store';
import ConsentApiService from 'src/core/consent/consent.api.service';
import broadcastConsentChanges from 'src/core/consent/broadcast-changes';

/**
 * @private
 */
export default async function initConsentStore(): Promise<void> {
    /**
     * @private
     */
    Shopware.Service().register('consentApiService', (serviceContainer) => {
        return new ConsentApiService(Shopware.Application.getContainer('init').httpClient, serviceContainer.loginService);
    });

    const consentStore = useConsentStore();

    try {
        await consentStore.update();
    } catch {
        // keep empty store and wait for next update interval
    }

    setInterval(() => {
        void consentStore.update();
    }, 300000); // every 5 minutes

    broadcastConsentChanges();
}
