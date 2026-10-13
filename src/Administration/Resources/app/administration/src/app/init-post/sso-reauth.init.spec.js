/**
 * @sw-package framework
 */
import initializeSsoReauth from './sso-reauth.init';

describe('src/app/init-post/sso-reauth.init.ts', () => {
    const loginListeners = [];
    const push = jest.fn();
    const createNotification = jest.fn();
    const complete = jest.fn(() => null);

    beforeAll(() => {
        Shopware.Service().register('loginService', () => ({
            addOnLoginListener: (listener) => loginListeners.push(listener),
        }));
        Shopware.Service().register('ssoReauthService', () => ({ complete }));
    });

    beforeEach(() => {
        sessionStorage.clear();
        loginListeners.length = 0;
        jest.clearAllMocks();
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue({ $t: (key) => key });
        Shopware.Application.view = { router: { push } };
        jest.spyOn(Shopware.Store.get('notification'), 'createNotification').mockImplementation(createNotification);
    });

    it('registers a login listener', async () => {
        await initializeSsoReauth();

        expect(loginListeners).toHaveLength(1);
    });

    it('ignores logins that are not SSO logins', async () => {
        await initializeSsoReauth();

        loginListeners[0]();

        expect(complete).not.toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('completes an SSO login that did not come from the modal without returning anywhere', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');
        await initializeSsoReauth();

        loginListeners[0]();

        expect(complete).toHaveBeenCalledTimes(1);
        expect(push).not.toHaveBeenCalled();
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('returns to the remembered route and asks to repeat the action', async () => {
        sessionStorage.setItem('sw-sso-session', 'true');
        complete.mockReturnValueOnce('/sw/settings/language-pack');
        await initializeSsoReauth();

        loginListeners[0]();

        expect(push).toHaveBeenCalledWith('/sw/settings/language-pack');
        expect(createNotification).toHaveBeenCalledWith({
            variant: 'info',
            title: 'global.sw-verify-user-modal.ssoReauthCompletedTitle',
            message: 'global.sw-verify-user-modal.ssoReauthCompletedMessage',
        });
    });
});
