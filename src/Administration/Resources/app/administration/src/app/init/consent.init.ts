/**
 * @sw-package framework:fundamentals
 */
import useConsentStore from 'src/core/consent/consent.store';
import ConsentApiService from '../../core/consent/consent.api.service';


/**
 * @private
 */
export default async function initConsentStore(): Promise<void> {
    /**
     * @private
     */
    Shopware.Service().register('consentApiService',(serviceContainer) => {
        return new ConsentApiService(
            Shopware.Application.getContainer('init').httpClient,
            serviceContainer.loginService,
        );
    });


    const consentStore = useConsentStore();

    await consentStore.update();
}
