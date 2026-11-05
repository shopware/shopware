/**
 * @sw-package framework
 */

import 'src/index';

jest.mock('src/core/shopware', () => jest.fn());
jest.mock('src/app/main', () => jest.fn());

describe('src/index', () => {
    afterEach(() => {
        window.name = '';
        delete window.opener;
        delete window.startApplication;
    });

    it('should only report back to the opener in the SSO re-authentication window', async () => {
        const postMessage = jest.fn();
        jest.spyOn(window, 'close').mockImplementation(() => {});
        window.name = 'sw-sso-reauth';
        Object.defineProperty(window, 'opener', { value: { postMessage }, configurable: true, writable: true });
        window.startApplication = jest.fn();

        await jest.isolateModulesAsync(async () => {
            await import('src/index');
        });
        await flushPromises();

        expect(postMessage).toHaveBeenCalledWith('sw-sso-reauth-complete', window.location.origin);
        expect(window.startApplication).not.toHaveBeenCalled();
    });

    // eslint-disable-next-line jest/expect-expect
    it('should import the core and the app', () => {
        /**
         * Direct imports can't be tested. Only exported mocked values can be checked
         */
    });
});
