import type { LoginService } from './login.service';

/**
 * @package merchant-services
 *
 * @private
 */
export default function addUsageDataConsentListener(loginService: LoginService, serviceContainer: ServiceContainer) {
    loginService.addOnLoginListener(fetchUsageDataConsent);
    loginService.addOnLogoutListener(resetUsageDataConsent);

    async function fetchUsageDataConsent() {
        try {
            const consent = await serviceContainer.usageDataService.getConsent();

            Shopware.State.commit('usageData/updateConsent', consent);
        } catch {
            resetUsageDataConsent();
        }
    }

    function resetUsageDataConsent() {
        Shopware.State.commit('usageData/resetConsent');
    }
}
