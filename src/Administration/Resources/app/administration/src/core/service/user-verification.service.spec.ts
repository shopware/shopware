/**
 * @sw-package framework
 */

import 'src/app/store/user-verification.store';
import UserVerificationService from './user-verification.service';
import UserVerificationError from './user-verification.error';
import type SsoReauthService from './sso-reauth.service';
import type { LoginService } from './login.service';

function createToken(scopes: string[]): string {
    const payload = window.btoa(JSON.stringify({ scopes })).replace(/\+/g, '-').replace(/\//g, '_');

    return `header.${payload}.signature`;
}

describe('src/core/service/user-verification.service.ts', () => {
    const loginService = {
        getLoginTemplateConfig: jest.fn(() => Promise.resolve({ useDefault: true, url: 'https://shop.test/sso' })),
        getBearerAuthentication: jest.fn((section?: string) => {
            const auth = { access: createToken(['write']), refresh: 'refresh', expiry: 1 };

            return section ? auth[section as keyof typeof auth] : auth;
        }),
    } as unknown as LoginService;
    const reauthenticate = jest.fn(() => Promise.resolve());
    const ssoReauthService = { reauthenticate } as unknown as SsoReauthService;
    const createNotification = jest.fn();

    const createService = () => new UserVerificationService(loginService, ssoReauthService);
    const store = () => Shopware.Store.get('userVerification');

    beforeEach(() => {
        sessionStorage.clear();
        store().dismiss();
        jest.clearAllMocks();
        Shopware.Store.get('notification').createNotification = createNotification;
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue({
            $t: (key: string) => key,
        } as unknown as ReturnType<typeof Shopware.Application.getApplicationRoot>);
    });

    it('asks for the password through the modal and resolves with the verified context', async () => {
        const service = createService();
        const context = { ...Shopware.Context.api, authToken: { access: 'verified', refresh: 'r', expiry: 1 } };

        const result = service.verify();
        await flushPromises();

        expect(store().requested).toBe(true);
        expect(store().ssoAvailable).toBe(true);
        expect(reauthenticate).not.toHaveBeenCalled();

        service.complete(context);

        await expect(result).resolves.toBe(context);
        expect(store().requested).toBe(false);
    });

    it('offers no identity provider in the modal when the shop has none', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockResolvedValueOnce({ useDefault: true, url: '' });

        void createService()
            .verify()
            .catch(() => {});
        await flushPromises();

        expect(store().ssoAvailable).toBe(false);
    });

    it('falls back to the password prompt when the SSO configuration cannot be loaded', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockRejectedValueOnce(new Error('offline'));

        void createService()
            .verify()
            .catch(() => {});
        await flushPromises();

        expect(store().requested).toBe(true);
        expect(store().ssoAvailable).toBe(false);
    });

    it('rejects as cancelled when the modal closes without a verification', async () => {
        const service = createService();

        const result = service.verify();
        await flushPromises();
        service.cancel();

        await expect(result).rejects.toEqual(UserVerificationError.cancelled());
        expect(store().requested).toBe(false);
    });

    it('joins a running verification instead of asking twice', async () => {
        const service = createService();
        const context = { ...Shopware.Context.api };

        const first = service.verify();
        const second = service.verify();
        await flushPromises();
        service.complete(context);

        await expect(first).resolves.toBe(context);
        await expect(second).resolves.toBe(context);
        expect(loginService.getLoginTemplateConfig).toHaveBeenCalledTimes(1);
    });

    it('re-authenticates an SSO session at the identity provider without a modal', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');

        const result = await createService().verify();

        expect(reauthenticate).toHaveBeenCalledTimes(1);
        expect(store().requested).toBe(false);
        expect(result).toEqual({ ...Shopware.Context.api });
    });

    it('treats every session of a shop without password login as an SSO session', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockResolvedValueOnce({
            useDefault: false,
            url: 'https://shop.test/sso',
        });

        await createService().verify();

        expect(reauthenticate).toHaveBeenCalledTimes(1);
        expect(sessionStorage.getItem('sw-sso-session')).toBe('true');
    });

    it('rejects an SSO re-authentication the user cancelled without a notification', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');
        reauthenticate.mockRejectedValueOnce(UserVerificationError.cancelled());

        await expect(createService().verify()).rejects.toEqual(UserVerificationError.cancelled());
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('tells the user when the SSO re-authentication cannot start', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');
        reauthenticate.mockRejectedValueOnce(UserVerificationError.ssoWindowBlocked());

        await expect(createService().verify()).rejects.toEqual(UserVerificationError.ssoWindowBlocked());
        expect(createNotification).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'global.sw-verify-user-modal.failure.sso-window-blocked',
        });
    });

    it('rejects when the identity provider returned without a session', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');
        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(false);

        await expect(createService().verify()).rejects.toEqual(UserVerificationError.noSession());
        expect(createNotification).toHaveBeenCalledTimes(1);
    });

    it('switches from the modal to the identity provider', async () => {
        const service = createService();

        const result = service.verify();
        await flushPromises();
        expect(store().requested).toBe(true);

        await service.switchToSso();

        expect(store().requested).toBe(false);
        expect(reauthenticate).toHaveBeenCalledTimes(1);
        await expect(result).resolves.toEqual({ ...Shopware.Context.api });
    });

    it('rejects the modal request when the identity provider switch fails', async () => {
        const service = createService();
        reauthenticate.mockRejectedValueOnce(UserVerificationError.cancelled());

        const result = service.verify();
        await flushPromises();
        await service.switchToSso();

        await expect(result).rejects.toEqual(UserVerificationError.cancelled());
    });

    it('reads the verified scope from the access token', () => {
        const service = createService();

        expect(service.isVerified()).toBe(false);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(createToken(['write', 'user-verified']));
        expect(service.isVerified()).toBe(true);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce('not-a-jwt');
        expect(service.isVerified()).toBe(false);
    });

    it('reports an SSO session from the login marker or the shop configuration', async () => {
        const service = createService();

        await expect(service.isSsoSession()).resolves.toBe(false);

        sessionStorage.setItem('sw-sso-session', 'true');
        await expect(service.isSsoSession()).resolves.toBe(true);
    });
});
