import { CookieStorage } from 'cookie-storage';

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be removed without replacement.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeCookies(): void {
    const loginService = Shopware.Service('loginService');
    const context = Shopware.State.get('context').api;
    const cookieStorage = loginService.getStorage();

    return loginService.addOnLogoutListener(() => {
        // With NEXT-18964 the storage options for cookies changed. This will make sure that old cookies are cleared.
        if (cookieStorage.getItem(loginService.getStorageKey())) {
            let domain;

            if (typeof window === 'object') {
                domain = window.location.hostname;
            } else {
                // eslint-disable-next-line no-restricted-globals
                const url = new URL(self.location.origin);
                domain = url.hostname;
            }

            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            const path = context.basePath! + context.pathInfo!;

            // rebuild the old storage
            const oldStorage = new CookieStorage(
                {
                    path: path,
                    domain: domain,
                    secure: false,
                    sameSite: 'Strict',
                },
            );

            oldStorage.clear();
        }
    });
}
