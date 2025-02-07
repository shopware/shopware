/**
 * @sw-package data-services
 *
 * @private
 */
export default function initUsageData(): Promise<void> {
    return new Promise<void>((resolve) => {
        const loginService = Shopware.Service('loginService');
        const usageDataApiService = Shopware.Service('usageDataService');

        if (!loginService.isLoggedIn()) {
            Shopware.Store.get('usageData').resetConsent();

            resolve();

            return;
        }

        usageDataApiService
            .getConsent()
            .then((usageData) => {
                Shopware.Store.get('usageData').updateConsent(usageData);
            })
            .catch(() => {
                Shopware.Store.get('usageData').resetConsent();
            })
            .finally(() => {
                resolve();
            });
    });
}
