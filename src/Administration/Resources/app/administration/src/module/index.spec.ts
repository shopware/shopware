/**
 * @sw-package framework
 */
import { login, ssoError } from 'src/module';

describe('src/module/index.js', () => {
    it('should load the login and the inactivity login module for the login boot', () => {
        expect(login()).toHaveLength(2);
        expect(Shopware.Module.getModuleRegistry().has('sw-login')).toBe(true);
        expect(Shopware.Module.getModuleRegistry().has('sw-inactivity-login')).toBe(true);
    });

    it('should load the sso error module', () => {
        expect(ssoError()).toHaveLength(1);
        expect(Shopware.Module.getModuleRegistry().has('sw-sso-error')).toBe(true);
    });
});
