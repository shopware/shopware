import type { LoginService } from './login.service';
import useUsageData from '../../app/composables/use-usage-data';

/**
 * @sw-package data-services
 *
 * @private
 */
export default function addUsageDataConsentListener(loginService: LoginService, serviceContainer: ServiceContainer) {
    const usageDataStore = useUsageData();
    loginService.addOnLoginListener(fetchUsageDataConsent);
    loginService.addOnLogoutListener(resetUsageDataConsent);

    async function fetchUsageDataConsent() {
        try {
            const consent = await serviceContainer.usageDataService.getConsent();

            usageDataStore.updateConsent(consent);
        } catch {
            resetUsageDataConsent();
        }
    }

    function resetUsageDataConsent() {
        usageDataStore.resetConsent();
    }
}
