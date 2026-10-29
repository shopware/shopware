/**
 * @sw-package framework
 */

import SsoReauthService from './sso-reauth.service';
import UserVerificationError from './user-verification.error';
import { SSO_REAUTH_COMPLETE_MESSAGE, SSO_REAUTH_WINDOW_NAME } from '../helper/sso-reauth-window.helper';
import type { LoginService } from './login.service';

describe('src/core/service/sso-reauth.service.ts', () => {
    const popup = { closed: false };
    const openWindow = jest.fn(() => popup as Window);
    const listeners = new Set<(event: Event) => void>();
    const windowEvents = {
        addEventListener: jest.fn((type: string, listener: (event: Event) => void) => listeners.add(listener)),
        removeEventListener: jest.fn((type: string, listener: (event: Event) => void) => listeners.delete(listener)),
    } as unknown as Pick<Window, 'addEventListener' | 'removeEventListener'>;
    const loginService = {
        getLoginTemplateConfig: jest.fn(() =>
            Promise.resolve({ useDefault: false, url: 'https://shop.test/api/oauth/sso/auth?rdm=abc' }),
        ),
    } as unknown as LoginService;

    const postMessage = (origin: string, data: unknown) => {
        listeners.forEach((listener) => listener({ origin, data } as unknown as Event));
    };

    const createService = () =>
        new SsoReauthService(loginService, { openWindow, windowEvents, origin: 'https://shop.test', pollInterval: 10 });

    beforeEach(() => {
        jest.useFakeTimers();
        popup.closed = false;
        listeners.clear();
        jest.clearAllMocks();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('opens the identity provider with a forced login in the re-authentication window', async () => {
        const result = createService().reauthenticate();
        await jest.advanceTimersByTimeAsync(0);

        expect(openWindow).toHaveBeenCalledWith(
            'https://shop.test/api/oauth/sso/auth?rdm=abc&usePromptLogin=1',
            SSO_REAUTH_WINDOW_NAME,
            expect.stringContaining('popup'),
        );

        postMessage('https://shop.test', SSO_REAUTH_COMPLETE_MESSAGE);

        await expect(result).resolves.toBeUndefined();
        expect(windowEvents.removeEventListener).toHaveBeenCalledTimes(1);
    });

    it('ignores messages from other origins and other messages', async () => {
        const result = createService().reauthenticate();
        await jest.advanceTimersByTimeAsync(0);

        postMessage('https://evil.test', SSO_REAUTH_COMPLETE_MESSAGE);
        postMessage('https://shop.test', 'something-else');

        expect(windowEvents.removeEventListener).not.toHaveBeenCalled();

        postMessage('https://shop.test', SSO_REAUTH_COMPLETE_MESSAGE);
        await expect(result).resolves.toBeUndefined();
    });

    it('rejects as cancelled when the user closes the window', async () => {
        const result = createService().reauthenticate();
        const caught = result.catch((error: unknown) => error);
        await jest.advanceTimersByTimeAsync(0);

        popup.closed = true;
        await jest.advanceTimersByTimeAsync(10 + SsoReauthService.CLOSE_GRACE_PERIOD);

        await expect(caught).resolves.toEqual(UserVerificationError.cancelled());
        expect(windowEvents.removeEventListener).toHaveBeenCalledTimes(1);
    });

    it('lets a completion posted right before the close win over the cancel', async () => {
        const result = createService().reauthenticate();
        await jest.advanceTimersByTimeAsync(0);

        popup.closed = true;
        await jest.advanceTimersByTimeAsync(10);
        postMessage('https://shop.test', SSO_REAUTH_COMPLETE_MESSAGE);
        await jest.advanceTimersByTimeAsync(SsoReauthService.CLOSE_GRACE_PERIOD);

        await expect(result).resolves.toBeUndefined();
    });

    it('rejects when the browser blocked the window', async () => {
        openWindow.mockReturnValueOnce(null as unknown as Window);

        await expect(createService().reauthenticate()).rejects.toEqual(UserVerificationError.ssoWindowBlocked());
        expect(windowEvents.addEventListener).not.toHaveBeenCalled();
    });

    it('rejects when the SSO login is not configured', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockResolvedValueOnce({ useDefault: true, url: '' });

        await expect(createService().reauthenticate()).rejects.toEqual(UserVerificationError.ssoNotConfigured());
        expect(openWindow).not.toHaveBeenCalled();
    });
});
