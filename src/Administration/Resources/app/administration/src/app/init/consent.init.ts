/**
 * @sw-package framework:fundamentals
 */
import useConsentStore from 'src/core/consent/consent.store';
import ConsentApiService from 'src/core/consent/consent.api.service';
import broadcastConsentChanges from 'src/core/consent/broadcast-changes';
import { handleConsentRequest, handleConsentStatus } from 'src/core/consent/sdk-handler';

/**
 * @private
 */
export default async function initConsent(): Promise<void> {
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
        if (!Shopware.Service('loginService').isLoggedIn()) {
            return;
        }

        void consentStore.update();
    }, 300000); // every 5 minutes

    broadcastConsentChanges();

    Shopware.ExtensionAPI.handle('consentStatus', handleConsentStatus);
    Shopware.ExtensionAPI.handle('consentRequest', handleConsentRequest);
}
