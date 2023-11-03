/**
 * @package merchant-services
 *
 * @private
 */
export default function initUsageData(): Promise<void> {
    return new Promise<void>((resolve) => {
        const loginService = Shopware.Service('loginService');
        const usageDataApiService = Shopware.Service('usageDataService');

        if (!loginService.isLoggedIn()) {
            Shopware.State.commit('usageData/resetConsent');

            resolve();

            return;
        }

        usageDataApiService.getConsent().then((usageData) => {
            Shopware.State.commit('usageData/updateConsent', usageData);
        }).catch(() => {
            Shopware.State.commit('usageData/resetConsent');
        }).finally(() => {
            resolve();
        });
    });
}
