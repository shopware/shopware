/**
 * @sw-package framework
 */
import { getAmplitudeBrowserApiKeyPrefix } from './amplitude.browser-client';

/**
 * @private
 */
export default function clearAmplitudeCookies(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const loginService = Shopware.Service('loginService');
    const storage = loginService?.getStorage();

    if (!storage) {
        return;
    }

    const basePath = Shopware.Context?.api?.basePath;

    [
        `AMP_${getAmplitudeBrowserApiKeyPrefix()}`,
        `AMP_MKTG_${getAmplitudeBrowserApiKeyPrefix()}`,
    ].forEach((cookieName) => {
        if (typeof basePath === 'string') {
            storage.removeItem(cookieName, { path: basePath });
        }

        storage.removeItem(cookieName);
    });
}
