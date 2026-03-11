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

    const amplitudeCookieName = `AMP_${getAmplitudeBrowserApiKeyPrefix()}`;
    const amplitudeMarketingCookieName = `AMP_MKTG_${getAmplitudeBrowserApiKeyPrefix()}`;

    const cookieNames = document.cookie
        .split(';')
        .map((cookie) => cookie.trim().split('=')[0])
        .filter((cookieName) => cookieName === amplitudeCookieName || cookieName === amplitudeMarketingCookieName);

    if (cookieNames.length === 0) {
        return;
    }

    cookieNames.forEach((cookieName) => {
        expireCookie(cookieName);
    });
}

function expireCookie(cookieName: string): void {
    document.cookie = `${cookieName}=; Max-Age=0; path=/; SameSite=Lax`;
}
