import { CookieStorage } from 'cookie-storage';
import clearAmplitudeCookies from './amplitude.browser-storage';

describe('src/core/telemetry/amplitude/amplitude.browser-storage.ts', () => {
    let storage;

    beforeEach(() => {
        storage = new CookieStorage({
            path: '/',
            domain: null,
            secure: false,
            sameSite: 'Lax',
        });
        Shopware.Service = jest.fn((serviceName) => {
            if (serviceName === 'loginService') {
                return {
                    getStorage: () => storage,
                };
            }

            return undefined;
        });
        global.Shopware = {
            ...global.Shopware,
            Service: Shopware.Service,
        };
        document.cookie = 'AMP_placeholde=; Max-Age=0; path=/; SameSite=Lax';
        document.cookie = 'AMP_MKTG_placeholde=; Max-Age=0; path=/; SameSite=Lax';
        document.cookie = 'other-cookie=; Max-Age=0; path=/; SameSite=Lax';
    });

    it('removes only the documented amplitude cookies', () => {
        document.cookie = 'AMP_placeholde=test-value';
        document.cookie = 'AMP_MKTG_placeholde=test-value';
        document.cookie = 'other-cookie=test-value';

        clearAmplitudeCookies();

        expect(document.cookie).not.toContain('AMP_placeholde=');
        expect(document.cookie).not.toContain('AMP_MKTG_placeholde=');
        expect(document.cookie).toContain('other-cookie=test-value');
    });
});
