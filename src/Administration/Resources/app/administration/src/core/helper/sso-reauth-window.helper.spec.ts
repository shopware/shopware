/**
 * @sw-package framework
 */

import { completeSsoReauthWindow, SSO_REAUTH_COMPLETE_MESSAGE, SSO_REAUTH_WINDOW_NAME } from './sso-reauth-window.helper';

function createWindow(overrides: { name?: string; withOpener?: boolean; hash?: string } = {}) {
    const postMessage = jest.fn();
    const close = jest.fn();
    const win = {
        name: overrides.name ?? SSO_REAUTH_WINDOW_NAME,
        opener: overrides.withOpener === false ? null : { postMessage },
        location: { hash: overrides.hash ?? '', origin: 'https://shop.test' },
        close,
    } as unknown as Window;

    return { win, postMessage, close };
}

describe('src/core/helper/sso-reauth-window.helper.ts', () => {
    it('reports back to the opener and closes the re-authentication window', () => {
        const { win, postMessage, close } = createWindow();

        expect(completeSsoReauthWindow(win)).toBe(true);
        expect(postMessage).toHaveBeenCalledWith(SSO_REAUTH_COMPLETE_MESSAGE, 'https://shop.test');
        expect(close).toHaveBeenCalledTimes(1);
    });

    it('leaves a window alone that was not opened for the re-authentication', () => {
        const { win, postMessage, close } = createWindow({ name: '' });

        expect(completeSsoReauthWindow(win)).toBe(false);
        expect(postMessage).not.toHaveBeenCalled();
        expect(close).not.toHaveBeenCalled();
    });

    it('leaves a window alone that lost its opener', () => {
        const { win, close } = createWindow({ withOpener: false });

        expect(completeSsoReauthWindow(win)).toBe(false);
        expect(close).not.toHaveBeenCalled();
    });

    it('keeps the window open on the SSO error page', () => {
        const { win, postMessage, close } = createWindow({ hash: '#/sso/error' });

        expect(completeSsoReauthWindow(win)).toBe(false);
        expect(postMessage).not.toHaveBeenCalled();
        expect(close).not.toHaveBeenCalled();
    });
});
